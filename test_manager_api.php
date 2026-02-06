<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TESTING MANAGER TICKETS API ===\n\n";

// Find the Cavite manager
$manager = \App\Models\User::where('role', 'manager')
    ->where('branch', 'ASHCOL GENTRI CAVITE')
    ->first();

if (!$manager) {
    echo "No manager found!\n";
    exit();
}

echo "Manager: {$manager->email}\n";
echo "Branch: {$manager->branch}\n\n";

// Manually create a token for testing
$token = $manager->createToken('test-token')->plainTextToken;
echo "Token created (save this for testing): {$token}\n\n";

// Now test the actual API endpoint
$branch = \App\Models\Branch::where('name', $manager->branch)->first();
if (!$branch) {
    echo "ERROR: Branch not found!\n";
    exit();
}

echo "Branch ID: {$branch->id}\n\n";

// Query exactly as the API does
$tickets = \App\Models\Ticket::select([
    'id', 'ticket_id', 'title', 'description', 'service_type', 
    'address', 'contact', 'preferred_date', 
    'status_id', 'customer_id', 'branch_id', 'created_at', 'updated_at'
])
->with(['status:id,name,color', 'customer:id,firstName,lastName'])
->where('branch_id', $branch->id)
->orderBy('created_at', 'desc')
->get();

echo "=== API RESPONSE (as JSON) ===\n";
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
        'assigned_staff' => null,
        'branch' => null,
        'created_at' => $ticket->created_at->format('Y-m-d H:i:s'),
        'updated_at' => $ticket->updated_at->format('Y-m-d H:i:s'),
    ];
});

echo json_encode([
    'success' => true,
    'tickets' => $ticketData
], JSON_PRETTY_PRINT);

echo "\n\n=== SUMMARY ===\n";
echo "Total tickets for this manager: " . $tickets->count() . "\n";
foreach ($tickets as $ticket) {
    echo "- {$ticket->ticket_id}: {$ticket->status->name}\n";
}
