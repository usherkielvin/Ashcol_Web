<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the dashboard based on user role.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $this->adminDashboard($user);
        } elseif ($user->isTechnician()) {
            return $this->staffDashboard($user);
        } else {
            return $this->customerDashboard($user);
        }
    }

    /**
     * Admin dashboard with overview statistics.
     */
    private function adminDashboard(User $user): View
    {
        // Total statistics
        $totalTickets = Ticket::count();
        $totalUsers = User::count();
        $totalCustomers = User::where('role', User::ROLE_CUSTOMER)->count();
        $totalStaff = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_TECHNICIAN])->count();

        // Ticket statistics by status
        $ticketsByStatus = TicketStatus::withCount('tickets')->get();
        


        // Unassigned tickets
        $unassignedTickets = Ticket::whereNull('assigned_staff_id')->count();

        // Recent tickets
        $recentTickets = Ticket::with(['customer', 'assignedStaff', 'status'])
            ->latest()
            ->take(10)
            ->get();

        // Recent users
        $recentUsers = User::latest()->take(5)->get();

        return view('dashboard.admin', [
            'totalTickets' => $totalTickets,
            'totalUsers' => $totalUsers,
            'totalCustomers' => $totalCustomers,
            'totalStaff' => $totalStaff,
            'ticketsByStatus' => $ticketsByStatus,

            'unassignedTickets' => $unassignedTickets,
            'recentTickets' => $recentTickets,
            'recentUsers' => $recentUsers,
        ]);
    }

    /**
     * Technician dashboard with assigned tickets.
     */
    private function staffDashboard(User $user): View
    {
        // Assigned tickets
        $assignedTickets = Ticket::where('assigned_staff_id', $user->id)
            ->with(['customer', 'status'])
            ->latest()
            ->get();

        // Assigned tickets by status
        $assignedByStatus = Ticket::where('assigned_staff_id', $user->id)
            ->with('status')
            ->get()
            ->groupBy('status_id');

        // Pending updates (tickets that need attention)
        $pendingStatusIds = TicketStatus::whereIn('name', ['Pending', 'Scheduled', 'Ongoing'])->pluck('id');
        $pendingUpdates = Ticket::where('assigned_staff_id', $user->id)
            ->whereIn('status_id', $pendingStatusIds)
            ->with(['customer', 'status'])
            ->latest()
            ->get();

        // Unassigned tickets (technicians can see these to assign themselves)
        $unassignedTickets = Ticket::whereNull('assigned_staff_id')
            ->with(['customer', 'status'])
            ->latest()
            ->take(10)
            ->get();

        // Statistics
        $stats = [
            'total_assigned' => $assignedTickets->count(),
            'pending' => $pendingUpdates->count(),

        ];

        return view('dashboard.staff', [
            'assignedTickets' => $assignedTickets,
            'assignedByStatus' => $assignedByStatus,
            'pendingUpdates' => $pendingUpdates,
            'unassignedTickets' => $unassignedTickets,
            'stats' => $stats,
        ]);
    }

    /**
     * Customer dashboard with own tickets.
     */
    private function customerDashboard(User $user): View
    {
        // Customer's tickets
        $tickets = Ticket::where('customer_id', $user->id)
            ->with(['assignedStaff', 'status'])
            ->latest()
            ->get();

        // Tickets by status
        $ticketsByStatus = $tickets->groupBy('status_id');

        // Statistics
        $stats = [
            'total' => $tickets->count(),
            'open' => $tickets->whereIn('status.name', ['Pending', 'Scheduled'])->count(),
            'in_progress' => $tickets->where('status.name', 'Ongoing')->count(),
            'resolved' => $tickets->where('status.name', 'Completed')->count(),
            'closed' => $tickets->where('status.name', 'Cancelled')->count(),
        ];

        // Recent tickets
        $recentTickets = $tickets->take(5);

        return view('dashboard.customer', [
            'tickets' => $tickets,
            'ticketsByStatus' => $ticketsByStatus,
            'stats' => $stats,
            'recentTickets' => $recentTickets,
        ]);
    }
}

