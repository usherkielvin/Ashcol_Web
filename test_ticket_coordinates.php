<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Ticket;
use App\Models\User;

echo "=== Checking Ticket Coordinates ===\n\n";

// Get all tickets with in-progress status
$tickets = Ticket::with(['customer', 'status'])
    ->whereHas('status', function($q) {
        $q->where('name', 'like', '%progress%')
          ->orWhere('name', 'like', '%ongoing%');
    })
    ->get();

echo "Found " . $tickets->count() . " in-progress tickets\n\n";

foreach ($tickets as $ticket) {
    echo "Ticket ID: {$ticket->ticket_id}\n";
    echo "Customer: {$ticket->customer->firstName} {$ticket->customer->lastName} (ID: {$ticket->customer_id})\n";
    echo "Status: {$ticket->status->name}\n";
    echo "Address: {$ticket->address}\n";
    echo "Customer Latitude: " . ($ticket->customer->latitude ?? 'NULL') . "\n";
    echo "Customer Longitude: " . ($ticket->customer->longitude ?? 'NULL') . "\n";
    echo "---\n\n";
}

// Check all customers
echo "\n=== All Customer Coordinates ===\n\n";
$customers = User::where('role', 'customer')->get();

foreach ($customers as $customer) {
    echo "Customer: {$customer->firstName} {$customer->lastName} (ID: {$customer->id})\n";
    echo "Email: {$customer->email}\n";
    echo "Latitude: " . ($customer->latitude ?? 'NULL') . "\n";
    echo "Longitude: " . ($customer->longitude ?? 'NULL') . "\n";
    echo "---\n\n";
}

