<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Checking Tickets Data ===\n";

$tickets = \App\Models\Ticket::with('customer')->get();
echo "Total tickets: " . $tickets->count() . "\n";

foreach ($tickets as $ticket) {
    echo "Ticket ID: " . $ticket->ticket_id . "\n";
    echo "Customer ID: " . $ticket->customer_id . "\n";
    if ($ticket->customer) {
        echo "Customer Role: " . $ticket->customer->role . "\n";
        echo "Customer Name: " . $ticket->customer->firstName . " " . $ticket->customer->lastName . "\n";
    } else {
        echo "Customer: NULL\n";
    }
    echo "---\n";
}

$customers = \App\Models\User::where('role', 'customer')->get();
echo "\nCustomer users: " . $customers->count() . "\n";
foreach ($customers as $customer) {
    echo "Customer ID: " . $customer->id . " - " . $customer->firstName . " " . $customer->lastName . " (" . $customer->email . ")\n";
}