<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    /**
     * Get user's tickets
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Ticket::with(['customer', 'assignedStaff', 'status', 'branch']);
        
        // Filter based on user role
        if ($user->isCustomer()) {
            $query->where('customer_id', $user->id);
        } elseif ($user->isManager() || $user->isStaff()) {
            // Managers and staff see tickets from their branch
            if ($user->branch) {
                $query->whereHas('branch', function ($q) use ($user) {
                    $q->where('name', $user->branch);
                });
            }
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
            ],
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
}