<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Illuminate\Console\Command;

class CreateTestTicket extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ticket:create-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a test ticket assigned to a technician';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $employee = User::where('role', 'technician')->first();
        $customer = User::where('role', 'customer')->first();
        $status = TicketStatus::where('name', 'Pending')->first();

        if (!$employee) {
            $this->error('No technician found in database');
            return Command::FAILURE;
        }

        if (!$customer) {
            $this->error('No customer found in database');
            return Command::FAILURE;
        }

        if (!$status) {
            $this->error('No "Pending" status found in database');
            return Command::FAILURE;
        }

        $ticket = Ticket::create([
            'ticket_id' => 'TEST-' . time(),
            'title' => 'Test Technician Ticket',
            'description' => 'This is a test ticket assigned to a technician to verify the API works correctly',
            'service_type' => 'Maintenance',
            'address' => '123 Test Street, Test City',
            'contact' => '123-456-7890',
            'priority' => 'medium',
            'customer_id' => $customer->id,
            'assigned_staff_id' => $employee->id,
            'status_id' => $status->id,
        ]);

        $this->info("Created test ticket: {$ticket->ticket_id}");
        $this->info("Assigned to technician: {$employee->username} ({$employee->email})");
        $this->info("Customer: {$customer->username} ({$customer->email})");
        $this->info("Status: {$status->name}");

        return Command::SUCCESS;
    }
}