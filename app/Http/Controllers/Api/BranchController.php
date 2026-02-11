<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BranchController extends Controller
{
    /**
     * Get all active branches
     */
    public function index(Request $request)
    {
        try {
            $branches = Branch::active()->get();
            
            return response()->json([
                'success' => true,
                'branches' => $branches->map(function ($branch) {
                    // Get manager for this branch
                    $manager = \App\Models\User::where('branch', $branch->name)
                        ->where('role', \App\Models\User::ROLE_MANAGER)
                        ->first();
                    
                    // Get employee count (technicians only, not including manager)
                    $employeeCount = \App\Models\User::where('branch', $branch->name)
                        ->whereIn('role', [\App\Models\User::ROLE_TECHNICIAN])
                        ->count();
                    
                    return [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'location' => $branch->location,
                        'address' => $branch->address,
                        'latitude' => $branch->latitude,
                        'longitude' => $branch->longitude,
                        'isActive' => $branch->is_active,
                        'manager' => $manager ? $manager->firstName . ' ' . $manager->lastName : null,
                        'employee_count' => $employeeCount,
                        'description' => $branch->location, // Using location as description for now
                    ];
                }),
                'total_branches' => $branches->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch branches: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branches',
            ], 500);
        }
    }

    /**
     * Get branch reports with ticket statistics
     */
    public function reports(Request $request)
    {
        try {
            $user = $request->user();
            
            // Only admins can access branch reports
            if (!$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view branch reports',
                ], 403);
            }
            
            $branches = Branch::active()->get();
            
            return response()->json([
                'success' => true,
                'branches' => $branches->map(function ($branch) {
                    // Get ticket counts by status
                    $completedCount = \App\Models\Ticket::where('branch_id', $branch->id)
                        ->whereHas('status', function ($q) {
                            $q->where('name', 'Completed');
                        })
                        ->count();
                    
                    $cancelledCount = \App\Models\Ticket::where('branch_id', $branch->id)
                        ->whereHas('status', function ($q) {
                            $q->where('name', 'Cancelled');
                        })
                        ->count();
                    
                    // Get manager for this branch
                    $manager = \App\Models\User::where('branch', $branch->name)
                        ->where('role', \App\Models\User::ROLE_MANAGER)
                        ->first();
                    
                    return [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'location' => $branch->location,
                        'completed_count' => $completedCount,
                        'cancelled_count' => $cancelledCount,
                        'total_tickets' => $completedCount + $cancelledCount,
                        'manager' => $manager ? $manager->firstName . ' ' . $manager->lastName : null,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch branch reports: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branch reports',
            ], 500);
        }
    }
    
    /**
     * Get detailed tickets for a specific branch
     */
    public function branchTickets(Request $request, $branchId)
    {
        try {
            $user = $request->user();
            
            // Only admins can access branch reports
            if (!$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view branch tickets',
                ], 403);
            }
            
            $branch = Branch::find($branchId);
            
            if (!$branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Branch not found',
                ], 404);
            }
            
            // Get tickets filtered by status if provided
            $query = \App\Models\Ticket::with(['customer', 'assignedStaff', 'status'])
                ->where('branch_id', $branchId);
            
            // Filter by status if provided (completed or cancelled)
            if ($request->has('status')) {
                $statusFilter = $request->status;
                if (in_array($statusFilter, ['completed', 'cancelled'])) {
                    $query->whereHas('status', function ($q) use ($statusFilter) {
                        $q->where('name', ucfirst($statusFilter));
                    });
                }
            }
            
            $tickets = $query->latest()->get();
            
            return response()->json([
                'success' => true,
                'branch' => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'location' => $branch->location,
                ],
                'tickets' => $tickets->map(function ($ticket) {
                    return [
                        'id' => $ticket->id,
                        'ticket_id' => $ticket->ticket_id,
                        'title' => $ticket->title,
                        'description' => $ticket->description,
                        'service_type' => $ticket->service_type,
                        'amount' => $ticket->amount,
                        'address' => $ticket->address,
                        'contact' => $ticket->contact,
                        'preferred_date' => $ticket->preferred_date?->format('Y-m-d'),
                        'status' => $ticket->status->name ?? 'Unknown',
                        'status_detail' => $ticket->status_detail,
                        'status_color' => $ticket->status->color ?? '#gray',
                        'customer_name' => $ticket->customer->firstName . ' ' . $ticket->customer->lastName,
                        'assigned_staff' => $ticket->assignedStaff ? $ticket->assignedStaff->firstName . ' ' . $ticket->assignedStaff->lastName : null,
                        'image_path' => $ticket->image_path,
                        'created_at' => $ticket->created_at->format('Y-m-d H:i:s'),
                        'updated_at' => $ticket->updated_at->format('Y-m-d H:i:s'),
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch branch tickets: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branch tickets',
            ], 500);
        }
    }

    /**
     * Sync branches to Firestore (manual trigger)
     * Note: Requires google/cloud-firestore with gRPC extension
     * This is a placeholder for when the extension is available
     */
    public function syncToFirestore(Request $request)
    {
        try {
            $firestoreService = new \App\Services\FirestoreService();
            $db = $firestoreService->database();
            $branches = Branch::active()->get();
            
            foreach ($branches as $branch) {
                $db->collection('branches')
                    ->document((string)$branch->id)
                    ->set([
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'location' => $branch->location,
                        'address' => $branch->address,
                        'latitude' => (float)$branch->latitude,
                        'longitude' => (float)$branch->longitude,
                        'isActive' => (bool)$branch->is_active,
                        'updatedAt' => new \DateTime(),
                    ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => "Synced {$branches->count()} branches to Firestore",
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync branches: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync branches: ' . $e->getMessage(),
            ], 500);
        }
    }
}
