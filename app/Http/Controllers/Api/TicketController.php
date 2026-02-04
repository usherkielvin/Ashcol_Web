<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\FirestoreService;

class TicketController extends Controller
{
    /**
     * Simple test endpoint for debugging
     */
    public function test(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'message' => 'API is working',
            'user' => [
                'id' => $user->id,
                'name' => $user->firstName . ' ' . $user->lastName,
                'email' => $user->email,
                'role' => $user->role,
                'is_customer' => $user->isCustomer(),
            ],
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
    
    /**
     * Get user's tickets
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        Log::info('TicketController@index called', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'is_customer' => $user->isCustomer()
        ]);
        
        $query = Ticket::with(['customer', 'assignedStaff', 'status', 'branch']);
        
        // Filter based on user role
        if ($user->isCustomer()) {
            Log::info('Filtering tickets for customer', ['customer_id' => $user->id]);
            $query->where('customer_id', $user->id);
        } elseif ($user->isManager()) {
            // Managers see tickets from their branch
            if ($user->branch) {
                $query->whereHas('branch', function ($q) use ($user) {
                    $q->where('name', $user->branch);
                });
            }
        } elseif ($user->isStaff()) {
            // Staff/Employees see only tickets assigned to them
            Log::info('Filtering tickets for staff/employee', ['staff_id' => $user->id]);
            $query->where('assigned_staff_id', $user->id);
        } elseif ($user->isAdmin()) {
            // Admins see all tickets
        }
        
        // Filter by status if provided
        if ($request->has('status') && $request->status) {
            $statusName = $request->status;
            $query->whereHas('status', function ($q) use ($statusName) {
                $q->where('name', 'like', '%' . $statusName . '%');
            });
        }
        
        $tickets = $query->latest()->get();
        
        Log::info('Tickets found', ['count' => $tickets->count()]);
        
        return response()->json([
            'success' => true,
            'tickets' => $tickets->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'ticket_id' => $ticket->ticket_id,
                    'title' => $ticket->title,
                    'description' => $ticket->description,
                    'service_type' => $ticket->service_type,
                    'address' => $ticket->address,
                    'contact' => $ticket->contact,
                    'preferred_date' => $ticket->preferred_date?->format('Y-m-d'),

                    'status' => $ticket->status->name ?? 'Unknown',
                    'status_color' => $ticket->status->color ?? '#gray',
                    'customer_name' => $ticket->customer->firstName . ' ' . $ticket->customer->lastName,
                    'assigned_staff' => $ticket->assignedStaff ? $ticket->assignedStaff->firstName . ' ' . $ticket->assignedStaff->lastName : null,
                    'branch' => $ticket->branch->name ?? null,
                    'image_path' => $ticket->image_path,
                    'created_at' => $ticket->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $ticket->updated_at->format('Y-m-d H:i:s'),
                ];
            }),
        ]);
    }
    
    /**
     * Get specific ticket details
     */
    public function show(Request $request, $ticketId)
    {
        $user = $request->user();
        
        $ticket = Ticket::with(['customer', 'assignedStaff', 'status', 'branch', 'comments.user'])
            ->where('ticket_id', $ticketId)
            ->first();
            
        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }
        
        // Check authorization
        if ($user->isCustomer() && $ticket->customer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this ticket',
            ], 403);
        }
        
        if (($user->isManager() || $user->isStaff()) && $user->branch) {
            if (!$ticket->branch || $ticket->branch->name !== $user->branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this ticket',
                ], 403);
            }
        }
        
        return response()->json([
            'success' => true,
            'ticket' => [
                'id' => $ticket->id,
                'ticket_id' => $ticket->ticket_id,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'service_type' => $ticket->service_type,
                'address' => $ticket->address,
                'contact' => $ticket->contact,
                'preferred_date' => $ticket->preferred_date?->format('Y-m-d'),

                'status' => $ticket->status->name ?? 'Unknown',
                'status_color' => $ticket->status->color ?? '#gray',
                'customer_name' => $ticket->customer->firstName . ' ' . $ticket->customer->lastName,
                'assigned_staff' => $ticket->assignedStaff ? $ticket->assignedStaff->firstName . ' ' . $ticket->assignedStaff->lastName : null,
                'branch' => $ticket->branch->name ?? null,
                'image_path' => $ticket->image_path,
                'latitude' => $ticket->customer->latitude ?? 0,
                'longitude' => $ticket->customer->longitude ?? 0,
                'created_at' => $ticket->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $ticket->updated_at->format('Y-m-d H:i:s'),
                'comments' => $ticket->comments->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'comment' => $comment->comment,
                        'user_name' => $comment->user->firstName . ' ' . $comment->user->lastName,
                        'user_role' => $comment->user->role,
                        'created_at' => $comment->created_at->format('Y-m-d H:i:s'),
                    ];
                }),
            ],
        ]);
    }
    
    /**
     * Update ticket status (for managers/staff)
     */
    public function updateStatus(Request $request, $ticketId)
    {
        $user = $request->user();
        
        // Allow managers, staff, admins, and technicians (employee role)
        $userRole = strtolower($user->role ?? '');
        if (
            !$user->isManager() &&
            !$user->isStaff() &&
            !$user->isAdmin() &&
            $userRole !== 'employee'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update ticket status',
            ], 403);
        }
        
        $validated = $request->validate([
            'status' => 'required|string|in:open,pending,accepted,in_progress,completed,cancelled,resolved,closed',
            'assigned_staff_id' => 'nullable|exists:users,id',
        ]);
        
        $ticket = Ticket::with(['branch', 'status'])->where('ticket_id', $ticketId)->first();
        
        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }
        
        // Extra safety: technicians can only update tickets assigned to them
        if ($userRole === 'employee') {
            if ((int) $ticket->assigned_staff_id !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'This ticket is not assigned to you',
                ], 403);
            }
        }
        
        // Check if user can manage this ticket (same branch) for managers/staff
        if (($user->isManager() || $user->isStaff()) && $user->branch) {
            if (!$ticket->branch || $ticket->branch->name !== $user->branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this ticket',
                ], 403);
            }
        }
        
        // Find status by name
        $statusMap = [
            'open' => 'Open',
            'pending' => 'Pending',
            'accepted' => 'Accepted',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
        ];
        
        $statusName = $statusMap[$validated['status']] ?? 'Pending';
        $status = TicketStatus::where('name', $statusName)->first();
        
        if (!$status) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status',
            ], 400);
        }
        
        $updateData = ['status_id' => $status->id];
        
        // If assigning staff, validate they belong to the same branch
        if (isset($validated['assigned_staff_id'])) {
            $staff = User::find($validated['assigned_staff_id']);
            if ($staff && ($staff->isStaff() || $staff->isManager())) {
                if ($user->branch && $staff->branch === $user->branch) {
                    $updateData['assigned_staff_id'] = $validated['assigned_staff_id'];
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Staff member must belong to the same branch',
                    ], 400);
                }
            }
        }
        
        $ticket->update($updateData);
        
        // Sync to Firestore
        try {
            $firestoreService = new FirestoreService(); 
            // We need to fetch fresh ticket data with relations for sync
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
        } catch (\Exception $e) {
            Log::error('Firestore sync failed in updateStatus: ' . $e->getMessage());
        }
        
        Log::info("Ticket {$ticket->ticket_id} status updated to {$statusName} by user {$user->id}");
        
        return response()->json([
            'success' => true,
            'message' => 'Ticket status updated successfully',
            'ticket' => [
                'ticket_id' => $ticket->ticket_id,
                'status' => $status->name,
                'status_color' => $status->color,
                'assigned_staff' => $ticket->assignedStaff ? $ticket->assignedStaff->firstName . ' ' . $ticket->assignedStaff->lastName : null,
                'scheduled_date' => $ticket->scheduled_date,
                'scheduled_time' => $ticket->scheduled_time,
                'schedule_notes' => $ticket->schedule_notes,
            ],
        ]);
    }
    
    /**
     * Set schedule for a ticket
     */
    public function setSchedule(Request $request, $ticketId)
    {
        $user = $request->user();
        
        if (!$user->isManager() && !$user->isStaff() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to set ticket schedule',
            ], 403);
        }
        
        $validated = $request->validate([
            'scheduled_date' => 'required|date',
            'scheduled_time' => 'required|date_format:H:i',
            'schedule_notes' => 'nullable|string',
            'assigned_staff_id' => 'required|exists:users,id',
        ]);
        
        $ticket = Ticket::with(['branch'])->where('ticket_id', $ticketId)->first();
        
        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }
        
        // Find the staff member to assign
        $staff = User::find($validated['assigned_staff_id']);
        
        if (!$staff) {
            Log::error("Assignment failed: Staff user not found", ['staff_id' => $validated['assigned_staff_id']]);
            return response()->json([
                'success' => false,
                'message' => 'Staff member not found',
            ], 404);
        }
        
        // Check if user has valid role (staff or manager)
        $staffRole = strtolower($staff->role ?? '');
        $isValidRole = in_array($staffRole, ['staff', 'employee', 'manager']);
        
        if (!$isValidRole) {
            Log::error("Assignment failed: Invalid role", [
                'staff_id' => $staff->id,
                'staff_role' => $staff->role,
                'staff_email' => $staff->email
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Selected user is not a valid technician. Role: ' . ($staff->role ?? 'unknown'),
            ], 400);
        }
        
        // When a technician is assigned, move ticket out of Pending into an ongoing state.
        // We use the existing "In Progress" status from TicketStatusSeeder.
        $inProgressStatus = TicketStatus::where('name', 'In Progress')->first();

        $updateData = [
            'scheduled_date' => $validated['scheduled_date'],
            'scheduled_time' => $validated['scheduled_time'],
            'schedule_notes' => $validated['schedule_notes'],
            'assigned_staff_id' => $validated['assigned_staff_id'],
        ];

        if ($inProgressStatus) {
            $updateData['status_id'] = $inProgressStatus->id;
        }

        try {
            $ticket->update($updateData);
            $ticket->refresh(); // Reload relations/status
            
            // Sync to Firestore
            try {
                $firestoreService = new FirestoreService(); 
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
            } catch (\Exception $e) {
                Log::error('Firestore sync failed in setSchedule: ' . $e->getMessage());
            }
            
            // Clear manager cache for this branch
            if ($ticket->branch) {
                self::clearManagerTicketsCache($ticket->branch->name);
            }
            
            // Clear employee cache for the assigned staff member
            Cache::forget("employee_tickets_{$staff->id}");
            
            // Send Firebase push notification to assigned employee
            try {
                $firebaseService = new \App\Services\FirebaseService();
                $firebaseService->notifyTicketAssigned($ticket->ticket_id, $staff, $user);
            } catch (\Exception $e) {
                // Log error but don't fail the assignment
                Log::warning('Failed to send FCM notification for ticket assignment', [
                    'ticket_id' => $ticket->ticket_id,
                    'error' => $e->getMessage()
                ]);
            }
            
            Log::info("Ticket {$ticket->ticket_id} schedule set by user {$user->id} and assigned to staff {$staff->id} (status set to In Progress)", [
                'ticket_id' => $ticket->ticket_id,
                'assigned_staff_id' => $staff->id,
                'assigned_staff_name' => trim(($staff->firstName ?? '') . ' ' . ($staff->lastName ?? '')),
                'scheduled_date' => $validated['scheduled_date'],
                'scheduled_time' => $validated['scheduled_time'],
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Ticket assigned and scheduled successfully',
                'ticket' => [
                    'ticket_id' => $ticket->ticket_id,
                    'scheduled_date' => $ticket->scheduled_date,
                    'scheduled_time' => $ticket->scheduled_time,
                    'schedule_notes' => $ticket->schedule_notes,
                    'assigned_staff' => trim(($staff->firstName ?? '') . ' ' . ($staff->lastName ?? '')),
                    'assigned_staff_id' => $staff->id,
                    'status' => $ticket->status->name ?? 'In Progress',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to assign ticket", [
                'ticket_id' => $ticket->ticket_id,
                'staff_id' => $staff->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign ticket: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Get employee's scheduled tickets
     */
    public function getEmployeeSchedule(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isStaff() && !$user->isManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view schedule',
            ], 403);
        }
        
        $tickets = Ticket::with(['customer', 'status', 'branch'])
            ->where('assigned_staff_id', $user->id)
            ->whereNotNull('scheduled_date')
            ->whereNotNull('scheduled_time')
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->get();
        
        return response()->json([
            'success' => true,
            'tickets' => $tickets->map(function ($ticket) {
                return [
                    'ticket_id' => $ticket->ticket_id,
                    'title' => $ticket->title,
                    'description' => $ticket->description,
                    'scheduled_date' => $ticket->scheduled_date,
                    'scheduled_time' => $ticket->scheduled_time,
                    'schedule_notes' => $ticket->schedule_notes,
                    'status' => $ticket->status->name ?? 'Unknown',
                    'status_color' => $ticket->status->color ?? '#gray',
                    'customer_name' => $ticket->customer->firstName . ' ' . $ticket->customer->lastName,
                    'address' => $ticket->address,
                    'service_type' => $ticket->service_type,
                    'branch' => $ticket->branch->name ?? null,
                ];
            }),
        ]);
    }
    
    /**
     * Accept ticket (shortcut for managers)
     */
    public function accept(Request $request, $ticketId)
    {
        $request->merge(['status' => 'accepted']);
        return $this->updateStatus($request, $ticketId);
    }
    
    /**
     * Reject/Cancel ticket (shortcut for managers)
     */
    public function reject(Request $request, $ticketId)
    {
        $request->merge(['status' => 'cancelled']);
        return $this->updateStatus($request, $ticketId);
    }
    
    /**
     * Get tickets for manager (branch-specific)
     */
    public function getManagerTickets(Request $request)
    {
        $startTime = microtime(true);
        \Log::info('Manager tickets request started');
        
        $user = $request->user();
        
        if (!$user->isManager() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view manager tickets',
            ], 403);
        }
        
        \Log::info('Authorization check passed', ['user_id' => $user->id, 'branch' => $user->branch]);
        
        // Cache key for manager tickets
        $cacheKey = "manager_tickets_{$user->id}_{$user->branch}";
        
        // Try to get from cache first (cache for 3 minutes for tickets)
        $cachedData = Cache::get($cacheKey);
        if ($cachedData) {
            \Log::info('Returning cached data', ['count' => count($cachedData)]);
            $endTime = microtime(true);
            return response()->json([
                'success' => true,
                'tickets' => $cachedData,
                'cached' => true,
            ])->header('X-Response-Time', round(($endTime - $startTime) * 1000, 2) . 'ms');
        }
        
        \Log::info('No cache found, querying database');
        
        // Get manager's branch ID for efficient filtering
        $managerBranchId = null;
        if ($user->isManager() && $user->branch) {
            $branchStart = microtime(true);
            // Use a simple cache for branch ID lookup to avoid repeated queries
            $branchCacheKey = "branch_id_{$user->branch}";
            $managerBranchId = Cache::remember($branchCacheKey, 3600, function () use ($user) {
                $branch = \App\Models\Branch::where('name', $user->branch)->first();
                return $branch ? $branch->id : null;
            });
            $branchEnd = microtime(true);
            \Log::info('Branch lookup completed', ['time' => ($branchEnd - $branchStart) * 1000 . 'ms', 'branch_id' => $managerBranchId]);
        }
        
        $queryStart = microtime(true);
        // Optimized query - select only needed columns and minimal eager loading
        $query = Ticket::select([
            'id', 'ticket_id', 'title', 'description', 'service_type', 
            'address', 'contact', 'preferred_date', 
            'status_id', 'customer_id', 'branch_id', 'created_at', 'updated_at'
        ])->with([
            'status:id,name,color', // Only load status id, name, color
            'customer:id,firstName,lastName' // Only load customer id and name fields
        ]);
        
        // Filter by branch_id directly (much faster than whereHas)
        if ($managerBranchId) {
            $query->where('branch_id', $managerBranchId);
        }
        
        $tickets = $query->orderBy('created_at', 'desc')->get();
        $queryEnd = microtime(true);
        \Log::info('Database query completed', ['time' => ($queryEnd - $queryStart) * 1000 . 'ms', 'count' => $tickets->count()]);
        
        $mapStart = microtime(true);
        $ticketData = $tickets->map(function ($ticket) {
            $customerName = '';
            if ($ticket->customer) {
                $firstName = $ticket->customer->firstName ?? '';
                $lastName = $ticket->customer->lastName ?? '';
                $customerName = trim($firstName . ' ' . $lastName);
            }
            
            return [
                'id' => $ticket->id,
                'ticket_id' => $ticket->ticket_id ?? '',
                'title' => $ticket->title ?? '',
                'description' => $ticket->description ?? '',
                'service_type' => $ticket->service_type ?? '',
                'address' => $ticket->address ?? '',
                'contact' => $ticket->contact ?? '',
                'preferred_date' => $ticket->preferred_date?->format('Y-m-d'),
                'status' => $ticket->status->name ?? 'Unknown',
                'status_color' => $ticket->status->color ?? '#gray',
                'customer_name' => $customerName,
                'assigned_staff' => null, // Simplified for now
                'branch' => null, // Simplified for now
                'created_at' => $ticket->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $ticket->updated_at->format('Y-m-d H:i:s'),
            ];
        });
        $mapEnd = microtime(true);
        \Log::info('Data mapping completed', ['time' => ($mapEnd - $mapStart) * 1000 . 'ms']);
        
        // Cache the result for 3 minutes (increased from 2 for better performance)
        Cache::put($cacheKey, $ticketData, 180);
        
        $endTime = microtime(true);
        \Log::info('Manager tickets request completed', ['total_time' => ($endTime - $startTime) * 1000 . 'ms']);
        
        return response()->json([
            'success' => true,
            'tickets' => $ticketData,
        ]);
    }

    /**
     * Clear manager tickets cache (called when tickets are updated)
     */
    public static function clearManagerTicketsCache($branchName)
    {
        // Clear cache for all managers in this branch
        $managers = \App\Models\User::where('role', 'manager')
            ->where('branch', $branchName)
            ->select('id')
            ->get();

        foreach ($managers as $manager) {
            $cacheKey = "manager_tickets_{$manager->id}_{$branchName}";
            Cache::forget($cacheKey);
        }
    }

    /**
     * Get tickets assigned to employee
     */
    public function getEmployeeTickets(Request $request)
    {
        $user = $request->user();
        
        // Check if user is staff, employee, or admin (more flexible role checking)
        $userRole = strtolower($user->role ?? '');
        $isValidRole = in_array($userRole, ['staff', 'employee']) || $user->isAdmin();
        
        if (!$isValidRole) {
            Log::warning("Unauthorized employee tickets access attempt", [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'user_email' => $user->email
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view employee tickets. Your role: ' . ($user->role ?? 'unknown'),
            ], 403);
        }
        
        Log::info("Employee tickets request", [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'user_email' => $user->email
        ]);
        
        // Support status filtering via query parameter
        $statusFilter = $request->query('status');
        if ($statusFilter) {
            $statusFilter = strtolower($statusFilter);
            // Map common status names
            $statusMap = [
                'pending' => ['Pending', 'pending', 'open', 'Open'],
                // Treat "in progress", "accepted", and "ongoing" as the same bucket
                'in_progress' => ['In Progress', 'in progress', 'accepted', 'Accepted', 'ongoing', 'Ongoing'],
                'completed' => ['Completed', 'completed', 'resolved', 'Resolved', 'closed', 'Closed'],
            ];
            
            // Filter application moved to after query initialization
        }
        
        // Cache key for employee tickets MUST include status so filters work correctly
        $cacheKey = "employee_tickets_{$user->id}_" . ($statusFilter ?: 'all');
        
        // Try cache first (2 minutes)
        $cachedData = Cache::get($cacheKey);
        if ($cachedData) {
            Log::info("Returning cached employee tickets", [
                'user_id' => $user->id,
                'ticket_count' => count($cachedData),
                'status_filter' => $statusFilter ?: 'all',
            ]);
            return response()->json([
                'success' => true,
                'tickets' => $cachedData,
                'cached' => true,
            ]);
        }
        
        $query = Ticket::with(['status', 'customer', 'branch', 'assignedStaff']);
        
        // Filter by assigned staff for employees - only show tickets assigned to this employee
        // Always filter by assigned_staff_id for non-admin users
        if (!$user->isAdmin()) {
            $query->where('assigned_staff_id', $user->id);
        }
        
        // Apply status filter if present
        if ($statusFilter) {
            $statusFilter = strtolower($statusFilter);
             $statusMap = [
                'pending' => ['Pending', 'pending', 'open', 'Open'],
                'in_progress' => ['In Progress', 'in progress', 'accepted', 'Accepted', 'ongoing', 'Ongoing'],
                'completed' => ['Completed', 'completed', 'resolved', 'Resolved', 'closed', 'Closed'],
            ];
            
            if (isset($statusMap[$statusFilter])) {
                $query->whereHas('status', function ($q) use ($statusMap, $statusFilter) {
                    $q->whereIn('name', $statusMap[$statusFilter]);
                });
            } else {
                 $query->whereHas('status', function ($q) use ($statusFilter) {
                    $q->whereRaw('LOWER(name) = ?', [$statusFilter]);
                });
            }
        }
        
        Log::info("Querying employee tickets", [
            'user_id' => $user->id,
            'assigned_staff_id_filter' => $user->id,
            'status_filter' => $statusFilter ?? 'all'
        ]);
        
        // Order by scheduled date first (if scheduled), then by creation date
        $tickets = $query->orderByRaw('CASE WHEN scheduled_date IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('scheduled_date', 'asc')
            ->orderBy('scheduled_time', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();
        
        Log::info("Found employee tickets", [
            'user_id' => $user->id,
            'ticket_count' => $tickets->count(),
            'ticket_ids' => $tickets->pluck('ticket_id')->toArray()
        ]);
        
        $ticketData = $tickets->map(function ($ticket) {
            $customerName = 'Unknown';
            if ($ticket->customer) {
                $firstName = $ticket->customer->firstName ?? '';
                $lastName = $ticket->customer->lastName ?? '';
                $customerName = trim($firstName . ' ' . $lastName) ?: 'Unknown';
            }
            
            return [
                'id' => $ticket->id,
                'ticket_id' => $ticket->ticket_id,
                'title' => $ticket->title ?? '',
                'description' => $ticket->description ?? '',
                'service_type' => $ticket->service_type ?? '',
                'address' => $ticket->address ?? '',
                'contact' => $ticket->contact ?? '',
                'preferred_date' => $ticket->preferred_date?->format('Y-m-d'),
                'scheduled_date' => $ticket->scheduled_date?->format('Y-m-d'),
                'schedule_notes' => $ticket->schedule_notes,
                'status' => $ticket->status->name ?? 'Unknown',
                'status_color' => $ticket->status->color ?? '#gray',
                'customer_name' => $customerName,
                'assigned_staff' => $ticket->assignedStaff ? trim(($ticket->assignedStaff->firstName ?? '') . ' ' . ($ticket->assignedStaff->lastName ?? '')) : null,
                'branch' => $ticket->branch->name ?? null,
                'latitude' => $ticket->customer->latitude ?? 0,
                'longitude' => $ticket->customer->longitude ?? 0,
                'created_at' => $ticket->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $ticket->updated_at->format('Y-m-d H:i:s'),
            ];
        });
        
        // Cache for 2 minutes, per-user and per-status
        Cache::put($cacheKey, $ticketData, 120);
        
        Log::info("Employee tickets response prepared", [
            'user_id' => $user->id,
            'ticket_count' => count($ticketData)
        ]);
        
        return response()->json([
            'success' => true,
            'tickets' => $ticketData,
        ]);
    }

    /**
     * Get employees for staff assignment
     */
    public function getEmployees(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isManager() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view employees',
            ], 403);
        }
        
        // Optimized query with specific column selection
        $query = User::select('id', 'firstName', 'lastName', 'email', 'role', 'branch')
            ->where('role', 'staff');
        
        // Filter by branch for managers (will use the new index)
        if ($user->isManager() && $user->branch) {
            $query->where('branch', $user->branch);
        }
        
        $employees = $query->get();
        
        $employeeData = $employees->map(function ($employee) {
            return [
                'id' => $employee->id,
                'firstName' => $employee->firstName,
                'lastName' => $employee->lastName,
                'email' => $employee->email,
                'role' => $employee->role,
                'branch' => $employee->branch,
            ];
        });
        
        // Cache employees for 5 minutes
        Cache::put($employeeCacheKey, $employeeData, 300);
        
        return response()->json([
            'success' => true,
            'employees' => $employeeData,
            'branch' => $user->branch,
        ]);
    }

    /**
     * Complete work with payment (for technicians)
     */
    public function completeWorkWithPayment(Request $request, $ticketId)
    {
        $user = $request->user();
        
        // Only staff/technicians can complete work
        $userRole = strtolower($user->role ?? '');
        if (!in_array($userRole, ['staff', 'employee']) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to complete work',
            ], 403);
        }
        
        $validated = $request->validate([
            'payment_method' => 'required|string|in:cash,online',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);
        
        $ticket = Ticket::with(['customer', 'branch', 'status'])->where('ticket_id', $ticketId)->first();
        
        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }
        
        // Verify ticket is assigned to this technician
        if ($ticket->assigned_staff_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket is not assigned to you',
            ], 403);
        }
        
        // Get completed status
        $completedStatus = TicketStatus::where('name', 'Completed')->first();
        if (!$completedStatus) {
            return response()->json([
                'success' => false,
                'message' => 'Completed status not found',
            ], 500);
        }
        
        // Use database transaction to ensure both ticket and payment are created together
        DB::beginTransaction();
        try {
            // Update ticket status to completed
            $ticket->update([
                'status_id' => $completedStatus->id,
            ]);
            
            // Find manager for this branch (if exists)
            $manager = null;
            if ($ticket->branch) {
                $manager = User::where('role', 'manager')
                    ->where('branch', $ticket->branch->name)
                    ->first();
            }
            
            // Create payment record
            $payment = Payment::create([
                'ticket_id' => $ticket->ticket_id,
                'ticket_table_id' => $ticket->id,
                'customer_id' => $ticket->customer_id,
                'technician_id' => $user->id,
                'manager_id' => $manager?->id,
                'payment_method' => $validated['payment_method'],
                'amount' => $validated['amount'],
                'status' => $validated['payment_method'] === 'cash' ? 'collected' : 'pending', // Cash is collected immediately
                'notes' => $validated['notes'] ?? null,
                'collected_at' => $validated['payment_method'] === 'cash' ? now() : null,
            ]);
            
            // Clear caches
            Cache::forget("employee_tickets_{$user->id}");
            if ($ticket->branch) {
                self::clearManagerTicketsCache($ticket->branch->name);
            }
            
            DB::commit();
            
            Log::info("Work completed with payment", [
                'ticket_id' => $ticket->ticket_id,
                'technician_id' => $user->id,
                'payment_method' => $validated['payment_method'],
                'amount' => $validated['amount'],
                'payment_id' => $payment->id,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Work completed successfully. Payment recorded.',
                'ticket' => [
                    'ticket_id' => $ticket->ticket_id,
                    'status' => $completedStatus->name,
                    'status_color' => $completedStatus->color,
                ],
                'payment' => [
                    'id' => $payment->id,
                    'payment_method' => $payment->payment_method,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'collected_at' => $payment->collected_at?->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to complete work with payment", [
                'ticket_id' => $ticketId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete work: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment history (for managers)
     */
    public function getPaymentHistory(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isManager() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view payment history',
            ], 403);
        }
        
        $query = Payment::with(['ticket.branch', 'customer', 'technician'])
            ->orderBy('created_at', 'desc');
        
        // Filter by manager's branch if manager
        if ($user->isManager() && $user->branch) {
            // Get branch ID for this manager's branch
            $branch = \App\Models\Branch::where('name', $user->branch)->first();
            if ($branch) {
                $query->whereHas('ticket', function ($q) use ($branch) {
                    $q->where('branch_id', $branch->id);
                });
            }
        }
        
        $payments = $query->get();
        
        return response()->json([
            'success' => true,
            'payments' => $payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'ticket_id' => $payment->ticket_id,
                    'customer_name' => $payment->customer ? ($payment->customer->firstName . ' ' . $payment->customer->lastName) : 'Unknown',
                    'technician_name' => $payment->technician ? ($payment->technician->firstName . ' ' . $payment->technician->lastName) : 'Unknown',
                    'payment_method' => $payment->payment_method,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'notes' => $payment->notes,
                    'collected_at' => $payment->collected_at?->format('Y-m-d H:i:s'),
                    'submitted_at' => $payment->submitted_at?->format('Y-m-d H:i:s'),
                    'completed_at' => $payment->completed_at?->format('Y-m-d H:i:s'),
                    'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                ];
            }),
        ]);
    }

    /**
     * Submit payment to manager (technician gives money to manager)
     */
    public function submitPaymentToManager(Request $request, $paymentId)
    {
        $user = $request->user();
        
        // Only staff/technicians can submit payments
        $userRole = strtolower($user->role ?? '');
        if (!in_array($userRole, ['staff', 'employee']) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to submit payment',
            ], 403);
        }
        
        $payment = Payment::find($paymentId);
        
        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        }
        
        // Verify payment belongs to this technician
        if ($payment->technician_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'This payment does not belong to you',
            ], 403);
        }
        
        // Only cash payments can be submitted
        if ($payment->payment_method !== 'cash') {
            return response()->json([
                'success' => false,
                'message' => 'Only cash payments can be submitted to manager',
            ], 400);
        }
        
        // Find manager for this branch
        $manager = null;
        if ($payment->ticket && $payment->ticket->branch) {
            $manager = User::where('role', 'manager')
                ->where('branch', $payment->ticket->branch->name)
                ->first();
        }
        
        if (!$manager) {
            return response()->json([
                'success' => false,
                'message' => 'Manager not found for this branch',
            ], 404);
        }
        
        $payment->update([
            'manager_id' => $manager->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        
        Log::info("Payment submitted to manager", [
            'payment_id' => $payment->id,
            'technician_id' => $user->id,
            'manager_id' => $manager->id,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Payment submitted to manager successfully',
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'submitted_at' => $payment->submitted_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Complete payment (manager confirms receipt)
     */
    public function completePayment(Request $request, $paymentId)
    {
        $user = $request->user();
        
        if (!$user->isManager() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to complete payment',
            ], 403);
        }
        
        $payment = Payment::find($paymentId);
        
        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        }
        
        // Verify payment is submitted to this manager
        if ($payment->manager_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'This payment is not submitted to you',
            ], 403);
        }
        
        $payment->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        
        Log::info("Payment completed by manager", [
            'payment_id' => $payment->id,
            'manager_id' => $user->id,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Payment completed successfully',
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'completed_at' => $payment->completed_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}