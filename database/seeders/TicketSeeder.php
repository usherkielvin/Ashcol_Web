<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $staff = User::where('email', 'technician@example.com')->first();
        $customer = User::where('email', 'customer@example.com')->first();

        if (!$admin || !$staff || !$customer) {
            return; // ensure base users exist
        }

        $open = TicketStatus::where('name', 'Open')->first();
        $inProgress = TicketStatus::where('name', 'In Progress')->first();
        $pending = TicketStatus::where('name', 'Pending')->first();
        $resolved = TicketStatus::where('name', 'Resolved')->first();

        // Customer ticket (unassigned)
        Ticket::create([
            'ticket_id' => 'ASH-' . str_pad(1, 4, '0', STR_PAD_LEFT),
            'title' => 'Cannot log in to portal',
            'description' => 'I am unable to log in with my credentials since this morning.',
            'customer_id' => $customer->id,
            'assigned_staff_id' => null,
            'status_id' => $open?->id ?? TicketStatus::getDefault()?->id,
            'priority' => 'high',
        ]);

        // Assigned to technician
        Ticket::create([
            'ticket_id' => 'ASH-' . str_pad(2, 4, '0', STR_PAD_LEFT),
            'title' => 'Payment not reflected',
            'description' => 'Payment made yesterday is not showing in my account.',
            'customer_id' => $customer->id,
            'assigned_staff_id' => $staff->id,
            'status_id' => $inProgress?->id ?? TicketStatus::getDefault()?->id,
            'priority' => 'urgent',
        ]);

        // Admin created ticket for customer
        Ticket::create([
            'ticket_id' => 'ASH-' . str_pad(3, 4, '0', STR_PAD_LEFT),
            'title' => 'Update account information',
            'description' => 'Please update my contact number and address.',
            'customer_id' => $customer->id,
            'assigned_staff_id' => $staff->id,
            'status_id' => $pending?->id ?? TicketStatus::getDefault()?->id,
            'priority' => 'medium',
        ]);

        // Resolved example
        Ticket::create([
            'ticket_id' => 'ASH-' . str_pad(4, 4, '0', STR_PAD_LEFT),
            'title' => 'Unable to download invoice',
            'description' => 'Download button results in 500 error.',
            'customer_id' => $customer->id,
            'assigned_staff_id' => $staff->id,
            'status_id' => $resolved?->id ?? TicketStatus::getDefault()?->id,
            'priority' => 'low',
        ]);
    }
}
