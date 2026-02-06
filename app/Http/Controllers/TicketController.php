<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTicketRequest;
use App\Models\Branch;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use App\Services\FirestoreService;

class TicketController extends Controller
{
    /**
     * Display a listing of tickets.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);
        $user = $request->user();
        $query = Ticket::with(['customer', 'assignedStaff', 'status', 'comments']);

        // Filter based on user role
        if ($user->isCustomer()) {
            // Customers see only their own tickets
            $query->where('customer_id', $user->id);
        } elseif ($user->isStaff()) {
            // Staff see only tickets assigned to them
            $query->where('assigned_staff_id', $user->id);
        } elseif ($user->isManager()) {
            // Managers see all tickets from their branch
            if ($user->branch) {
                $query->whereHas('branch', function ($q) use ($user) {
                    $q->where('name', $user->branch);
                });
            }
        }
        // Admins see all tickets (no filter)

        // Filter by status if provided
        // Filter by status if provided
        if ($request->has('status_id') && $request->status_id) {
            $query->where('status_id', $request->status_id);
        }

        // Search by title or description
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $tickets = $query->latest()->paginate(15);
        $statuses = TicketStatus::all();
        
        return view('tickets.index', [
            'tickets' => $tickets,
            'statuses' => $statuses,
        ]);
    }

    /**
     * Show the form for creating a new ticket.
     */
    public function create(): View
    {
        $this->authorize('create', Ticket::class);
        $statuses = TicketStatus::all();
        $staff = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_STAFF])->get();
        
        return view('tickets.create', [
            'statuses' => $statuses,
            'staff' => $staff,
        ]);
    }

    /**
     * Store a newly created ticket in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Ticket::class);
        $user = $request->user();

        // Check if it's an API request (multipart/form-data)
        $isApi = $request->is('api/*') || $request->expectsJson();

        if ($isApi) {
            // API validation for service tickets (from Android app)
            $validated = $request->validate([
                'title' => 'nullable|string|max:255',
                'description' => 'required|string|max:5000',
                'address' => 'required|string|max:255',
                'contact' => 'required|string|max:255',
                'service_type' => 'required|string|max:255',
                'preferred_date' => 'nullable|string|date',
                'image' => 'nullable|image|mimes:jpg,png,jpeg,gif|max:2048', // 2MB max
            ]);

            $data = $validated;
            unset($data['image']); // Don't pass uploaded file to create
            $data['preferred_date'] = !empty($validated['preferred_date']) ? $validated['preferred_date'] : null;
            $data['priority'] = Ticket::PRIORITY_MEDIUM; // Force default
            $data['customer_id'] = $user->id;

            // Always try to start customer tickets in a clear "Pending" state so
            // the customer Pending filter and manager review flow behave predictably.
            $pendingStatus = TicketStatus::where('name', 'Pending')->first();
            if ($pendingStatus) {
                $data['status_id'] = $pendingStatus->id;
            } else {
                $defaultStatus = TicketStatus::getDefault();
                if ($defaultStatus) {
                    $data['status_id'] = $defaultStatus->id;
                } else {
                    $firstStatus = TicketStatus::first();
                    if ($firstStatus) {
                        $data['status_id'] = $firstStatus->id;
                    } else {
                        // Auto-create a default "Open" status if none exists
                        $defaultStatus = TicketStatus::create([
                            'name' => 'Open',
                            'color' => '#10B981',
                            'is_default' => true,
                        ]);
                        $data['status_id'] = $defaultStatus->id;
                    }
                }
            }
            $data['title'] = $validated['title'] ?? ($validated['service_type'] . ' Request');
            $data['ticket_id'] = Ticket::generateTicketId();

            // Update user location from ticket address for branch assignment (if user has none)
            if (!$user->location && !empty($validated['address'])) {
                $user->update(['location' => $validated['address']]);
            }
            
            // Branch Assignment Logic
            $branchName = $user->branch;
            
            // If user has no branch, try to guess it from their profile or the ticket address
            if (empty($branchName)) {
                 // Try user's city/region first
                 $branchName = \App\Models\Branch::guessFromRegionCity($user->region, $user->city);
                 
                 // If failed, try parsing the ticket address
                 if (empty($branchName) && !empty($validated['address'])) {
                     $branchName = \App\Models\Branch::guessFromRegionCity(null, $validated['address']);
                 }
                 
                 // If we successfully guessed a branch, update the user's profile
                 if ($branchName) {
                     $user->update(['branch' => $branchName]);
                 }
            }

            // Assign branch - use matching branch or default to active
            $branch = $branchName
                ? Branch::where('name', $branchName)->active()->first()
                : null;
            
            // Fallback to first active branch only if absolutely necessary (logging it)
            if (!$branch) {
                $branch = Branch::active()->first();
                Log::warning("Ticket {$data['ticket_id']} assigned to default branch {$branch?->name} because no matching branch found for user {$user->id}");
            }

            if ($branch) {
                $data['branch_id'] = $branch->id;
                Log::info("Assigned branch {$branch->name} to ticket for user {$user->id}");
            }

            // Handle image upload
            if ($request->hasFile('image')) {
                $ticketsDir = public_path('tickets');
                if (!file_exists($ticketsDir)) {
                    mkdir($ticketsDir, 0755, true);
                }
                $image = $request->file('image');
                $imageName = time() . '_' . $image->getClientOriginalName();
                $image->move($ticketsDir, $imageName);
                $data['image_path'] = 'tickets/' . $imageName;
            }

            $ticket = Ticket::create($data);

            $ticket->load(['customer', 'status', 'branch']);

            // Clear manager tickets cache so branch manager sees new ticket immediately
            if ($ticket->branch) {
                \App\Http\Controllers\Api\TicketController::clearManagerTicketsCache($ticket->branch->name);
            }
            
            // Sync to Firestore
            try {
                $firestoreService = new FirestoreService();
                
                if ($firestoreService->isAvailable()) {
                    $syncedTicket = Ticket::with(['customer', 'assignedStaff', 'status', 'branch'])->find($ticket->id);
                    
                    $firestoreService->database()
                        ->collection('tickets')
                        ->document($syncedTicket->ticket_id)
                        ->set([
                            'id' => $syncedTicket->id,
                            'ticketId' => $syncedTicket->ticket_id,
                            'customerId' => $syncedTicket->customer_id,
                            'customerEmail' => $syncedTicket->customer->email ?? null,
                            'assignedTo' => $syncedTicket->assigned_staff_id,
                            'status' => $syncedTicket->status->name ?? 'Unknown',
                            'statusColor' => $syncedTicket->status->color ?? '#gray',
                            'serviceType' => $syncedTicket->service_type,
                            'description' => $syncedTicket->description,
                            'scheduledDate' => $syncedTicket->scheduled_date,
                            'scheduledTime' => $syncedTicket->scheduled_time,
                            'branch' => $syncedTicket->branch->name ?? null,
                            'updatedAt' => new \DateTime(),
                        ], ['merge' => true]);
                    
                    Log::info("Ticket {$syncedTicket->ticket_id} synced to Firestore successfully");
                } else {
                    Log::info("Firestore not available, skipping sync for ticket {$ticket->ticket_id}");
                }
            } catch (\Exception $e) {
                Log::error('Firestore sync failed in store (API): ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Ticket created successfully.',
                'ticket' => $ticket,
                'ticket_id' => $ticket->ticket_id,
                'status' => $ticket->status?->name ?? 'Pending',
            ], 201);
        } else {
            // Web form validation
            $rules = [
                'title' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string', 'max:5000'],
            ];

            // Admin and staff can assign customer and staff
            if ($user->isAdminOrStaff()) {
                $rules['customer_id'] = ['nullable', 'exists:users,id'];
                $rules['assigned_staff_id'] = ['nullable', 'exists:users,id'];
                $rules['status_id'] = ['nullable', 'exists:ticket_statuses,id'];
            }

            $validated = $request->validate($rules);

            $data = $validated;
            $data['ticket_id'] = Ticket::generateTicketId(); // Generate unique ticket ID
            $data['priority'] = Ticket::PRIORITY_MEDIUM; // Force default

            // Customers can only create tickets for themselves
            if ($user->isCustomer()) {
                $data['customer_id'] = $user->id;
                $data['status_id'] = TicketStatus::getDefault()->id;

                // Branch Assignment Logic for Web Customer
                $branchName = $user->branch;
                if (empty($branchName)) {
                     $branchName = \App\Models\Branch::guessFromRegionCity($user->region, $user->city);
                     if ($branchName) {
                         $user->update(['branch' => $branchName]);
                     }
                }

                $branch = $branchName
                    ? Branch::where('name', $branchName)->active()->first()
                    : Branch::active()->first();
                
                if ($branch) {
                    $data['branch_id'] = $branch->id;
                }
            }

            // If no staff assigned, set to null
            if (empty($data['assigned_staff_id'])) {
                $data['assigned_staff_id'] = null;
            }

            $ticket = Ticket::create($data);

            // Clear manager tickets cache so branch manager sees new ticket immediately
            if ($ticket->branch) {
                \App\Http\Controllers\Api\TicketController::clearManagerTicketsCache($ticket->branch->name);
            }

            // Sync to Firestore
            try {
                $firestoreService = new FirestoreService();
                
                if ($firestoreService->isAvailable()) {
                    $syncedTicket = Ticket::with(['customer', 'assignedStaff', 'status', 'branch'])->find($ticket->id);
                    
                    $firestoreService->database()
                        ->collection('tickets')
                        ->document($syncedTicket->ticket_id)
                        ->set([
                            'id' => $syncedTicket->id,
                            'ticketId' => $syncedTicket->ticket_id,
                            'customerId' => $syncedTicket->customer_id,
                            'customerEmail' => $syncedTicket->customer->email ?? null,
                            'assignedTo' => $syncedTicket->assigned_staff_id,
                            'status' => $syncedTicket->status->name ?? 'Unknown',
                            'statusColor' => $syncedTicket->status->color ?? '#gray',
                            'serviceType' => $syncedTicket->service_type,
                            'description' => $syncedTicket->description,
                            'scheduledDate' => $syncedTicket->scheduled_date,
                            'scheduledTime' => $syncedTicket->scheduled_time,
                            'branch' => $syncedTicket->branch->name ?? null,
                            'updatedAt' => new \DateTime(),
                        ], ['merge' => true]);
                    
                    Log::info("Ticket {$syncedTicket->ticket_id} synced to Firestore successfully");
                } else {
                    Log::info("Firestore not available, skipping sync for ticket {$ticket->ticket_id}");
                }
            } catch (\Exception $e) {
                Log::error('Firestore sync failed in store (Web): ' . $e->getMessage());
            }

            return redirect()->route('tickets.show', $ticket)
                ->with('success', 'Ticket created successfully.');
        }
    }

    /**
     * Display the specified ticket.
     */
    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);
        $ticket->load(['customer', 'assignedStaff', 'status', 'comments.user']);
        
        return view('tickets.show', [
            'ticket' => $ticket,
        ]);
    }

    /**
     * Show the form for editing the specified ticket.
     */
    public function edit(Ticket $ticket): View
    {
        $this->authorize('update', $ticket);
        $ticket->load(['customer', 'assignedStaff', 'status']);
        $statuses = TicketStatus::all();
        $staff = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_STAFF])->get();
        
        return view('tickets.edit', [
            'ticket' => $ticket,
            'statuses' => $statuses,
            'staff' => $staff,
        ]);
    }

    /**
     * Update the specified ticket in storage.
     */
    public function update(UpdateTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);
        $data = $request->validated();
        
        // If no staff assigned, set to null
        if (empty($data['assigned_staff_id'])) {
            $data['assigned_staff_id'] = null;
        }

        $ticket->update($data);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket updated successfully.');
    }

    /**
     * Remove the specified ticket from storage.
     */
    public function destroy(Ticket $ticket): RedirectResponse
    {
        $this->authorize('delete', $ticket);
        $ticket->delete();

        return redirect()->route('tickets.index')
            ->with('success', 'Ticket deleted successfully.');
    }
}

