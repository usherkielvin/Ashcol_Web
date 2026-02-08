<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check if there are tickets with assigned staff
$ticketsWithStaff = \App\Models\Ticket::whereNotNull('assigned_staff_id')->count();
echo "Tickets with assigned staff: " . $ticketsWithStaff . "\n";

// Check a few tickets with assigned staff
$tickets = \App\Models\Ticket::whereNotNull('assigned_staff_id')->limit(3)->get();
foreach($tickets as $ticket) {
    echo "Ticket: " . $ticket->ticket_id . " - Assigned to ID: " . $ticket->assigned_staff_id . "\n";
    if ($ticket->assignedStaff) {
        echo "  Assigned Staff Name: " . $ticket->assignedStaff->firstName . " " . $ticket->assignedStaff->lastName . "\n";
    } else {
        echo "  No assigned staff found for this ID\n";
    }
}

// Check if the relationship is working
$ticket = \App\Models\Ticket::first();
if ($ticket) {
    echo "\nFirst ticket ID: " . $ticket->id . "\n";
    echo "Assigned staff ID: " . $ticket->assigned_staff_id . "\n";
    echo "Assigned staff relationship loaded: " . ($ticket->assignedStaff ? 'Yes' : 'No') . "\n";
    if ($ticket->assignedStaff) {
        echo "Assigned staff name: " . $ticket->assignedStaff->firstName . " " . $ticket->assignedStaff->lastName . "\n";
    }
}