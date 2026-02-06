<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get a specific manager and simulate the API call
echo "=== SIMULATING MANAGER TICKETS API CALL ===\n\n";

$manager = \App\Models\User::where('role', 'manager')
    ->where('branch', 'ASHCOL GENTRI CAVITE')
    ->first();

if (!$manager) {
    echo "No manager found for ASHCOL GENTRI CAVITE\n";
    exit();
}

echo "Manager: {$manager->email}\n";
echo "Branch: '{$manager->branch}'\n\n";

// Simulate what the API does
$branchCacheKey = "branch_id_{$manager->branch}";
$branch = \App\Models\Branch::where('name', $manager->branch)->first();

if ($branch) {
    echo "Branch found in database:\n";
    echo "  ID: {$branch->id}\n";
    echo "  Name: '{$branch->name}'\n\n";
    
    $managerBranchId = $branch->id;
    
    // Query tickets just like the API does
    $tickets = \App\Models\Ticket::select([
        'id', 'ticket_id', 'title', 'description', 'service_type', 
        'address', 'contact', 'preferred_date', 
        'status_id', 'customer_id', 'branch_id', 'created_at'
    ])
    ->with(['status:id,name,color', 'customer:id,firstName,lastName'])
    ->where('branch_id', $managerBranchId)
    ->orderBy('created_at', 'desc')
    ->get();
    
    echo "Tickets found for branch_id {$managerBranchId}: " . $tickets->count() . "\n\n";
    
    foreach ($tickets as $ticket) {
        $customerName = $ticket->customer 
            ? trim(($ticket->customer->firstName ?? '') . ' ' . ($ticket->customer->lastName ?? ''))
            : 'Unknown';
        echo "- {$ticket->ticket_id}: {$ticket->title} (Customer: {$customerName}, Status: {$ticket->status->name})\n";
    }
} else {
    echo "Branch '{$manager->branch}' NOT FOUND in branches table!\n";
    echo "\nAll branches:\n";
    foreach (\App\Models\Branch::all() as $b) {
        echo "  - ID {$b->id}: '{$b->name}'\n";
    }
}

// Check cache
echo "\n=== CACHE CHECK ===\n";
$cacheKey = "manager_tickets_{$manager->id}_{$manager->branch}";
if (Cache::has($cacheKey)) {
    echo "Cache EXISTS for key: {$cacheKey}\n";
    $cachedData = Cache::get($cacheKey);
    echo "Cached tickets count: " . count($cachedData) . "\n";
} else {
    echo "No cache for key: {$cacheKey}\n";
}
