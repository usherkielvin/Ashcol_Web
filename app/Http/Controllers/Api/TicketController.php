<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
                    'priority' => $ticket->priority,
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
                'priority' => $ticket->priority,
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
        
        if (!$user->isManager() && !$user->isStaff() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update ticket status',
            ], 403);
        }
        
        $validated = $request->validate([
            'status' => 'required|string|in:pending,accepted,in_progress,completed,cancelled',
            'assigned_staff_id' => 'nullable|exists:users,id',
        ]);
        
        $ticket = Ticket::with(['branch'])->where('ticket_id', $ticketId)->first();
        
        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }
        
        // Check if user can manage this ticket (same branch)
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
            'pending' => 'Pending',
            'accepted' => 'Accepted',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
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
        
        // Check authorization
        if (($user->isManager() || $user->isStaff()) && $user->branch) {
            if (!$ticket->branch || $ticket->branch->name !== $user->branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to set schedule for this ticket',
                ], 403);
            }
        }
        
        // Validate staff belongs to same branch
        $staff = User::find($validated['assigned_staff_id']);
        if ($staff && ($staff->isStaff() || $staff->isManager())) {
            if ($user->branch && $staff->branch === $user->branch) {
                $ticket->update([
                    'scheduled_date' => $validated['scheduled_date'],
                    'scheduled_time' => $validated['scheduled_time'],
                    'schedule_notes' => $validated['schedule_notes'],
                    'assigned_staff_id' => $validated['assigned_staff_id'],
                ]);
                
                Log::info("Ticket {$ticket->ticket_id} schedule set by user {$user->id}");
                
                return response()->json([
                    'success' => true,
                    'message' => 'Ticket schedule set successfully',
                    'ticket' => [
                        'ticket_id' => $ticket->ticket_id,
                        'scheduled_date' => $ticket->scheduled_date,
                        'scheduled_time' => $ticket->scheduled_time,
                        'schedule_notes' => $ticket->schedule_notes,
                        'assigned_staff' => $staff->firstName . ' ' . $staff->lastName,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff member must belong to the same branch',
                ], 400);
            }
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Invalid staff assignment',
        ], 400);
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
                    'priority' => $ticket->priority,
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
        
        // Try to get from cache first (cache for 2 minutes for tickets)
        $cachedData = Cache::get($cacheKey);
        if ($cachedData) {
            \Log::info('Returning cached data');
            return response()->json([
                'success' => true,
                'tickets' => $cachedData,
                'cached' => true,
            ]);
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
        // Simplified query - remove some eager loading to speed up
        $query = Ticket::with(['status', 'customer']);
        
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
                'priority' => $ticket->priority ?? 'medium',
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
        
        // Cache the result for 2 minutes
        Cache::put($cacheKey, $ticketData, 120);
        
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
        
        if (!$user->isStaff() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view employee tickets',
            ], 403);
        }
        
        $query = Ticket::with(['status', 'customer', 'branch', 'assignedStaff']);
        
        // Filter by assigned staff for employees
        if ($user->isStaff()) {
            $query->where('assigned_staff_id', $user->id);
        }
        
        $tickets = $query->orderBy('created_at', 'desc')->get();
        
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
                    'priority' => $ticket->priority,
                    'status' => $ticket->status->name ?? 'Unknown',
                    'status_color' => $ticket->status->color ?? '#gray',
                    'customer_name' => $ticket->customer->firstName . ' ' . $ticket->customer->lastName,
                    'assigned_staff' => $ticket->assignedStaff ? $ticket->assignedStaff->firstName . ' ' . $ticket->assignedStaff->lastName : null,
                    'branch' => $ticket->branch->name ?? null,
                    'latitude' => $ticket->customer->latitude ?? 0,
                    'longitude' => $ticket->customer->longitude ?? 0,
                    'created_at' => $ticket->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $ticket->updated_at->format('Y-m-d H:i:s'),
                ];
            }),
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
        
        return response()->json([
            'success' => true,
            'employees' => $employees->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'firstName' => $employee->firstName,
                    'lastName' => $employee->lastName,
                    'email' => $employee->email,
                    'role' => $employee->role,
                    'branch' => $employee->branch,
                ];
            }),
        ]);
    }
}