<?php
// Debug script to check manager tickets issue
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Ticket;
use App\Models\Branch;

echo "=== Manager Tickets Debug Tool ===\n\n";

// 1. Check if we have managers
echo "1. Checking managers in database...\n";
$managers = User::where('role', 'manager')->get();
echo "Found " . $managers->count() . " managers:\n";
foreach ($managers as $manager) {
    echo "  - ID: {$manager->id}, Name: {$manager->firstName} {$manager->lastName}, Email: {$manager->email}, Branch: {$manager->branch}\n";
}

echo "\n2. Checking branches...\n";
$branches = Branch::all();
echo "Found " . $branches->count() . " branches:\n";
foreach ($branches as $branch) {
    echo "  - ID: {$branch->id}, Name: {$branch->name}, Manager: {$branch->manager}\n";
}

echo "\n3. Checking tickets...\n";
$tickets = Ticket::with(['status', 'customer', 'assignedStaff', 'branch'])->limit(10)->get();
echo "Found " . Ticket::count() . " total tickets, showing first 10:\n";
foreach ($tickets as $ticket) {
    echo "  - ID: {$ticket->id}, Ticket ID: {$ticket->ticket_id}, Status: {$ticket->status->name}, Branch ID: {$ticket->branch_id}, Assigned Staff ID: {$ticket->assigned_staff_id}\n";
    if ($ticket->branch) {
        echo "    Branch: {$ticket->branch->name}\n";
    }
    if ($ticket->assignedStaff) {
        echo "    Assigned to: {$ticket->assignedStaff->firstName} {$ticket->assignedStaff->lastName}\n";
    }
}

echo "\n4. Testing getManagerTickets for each manager...\n";
foreach ($managers as $manager) {
    echo "\n--- Testing Manager: {$manager->firstName} {$manager->lastName} ({$manager->email}) ---\n";
    
    if (empty($manager->branch)) {
        echo "❌ Manager has no branch assigned!\n";
        continue;
    }
    
    echo "Manager branch: {$manager->branch}\n";
    
    // Find branch by name
    $branch = Branch::where('name', $manager->branch)->first();
    if (!$branch) {
        echo "❌ Branch '{$manager->branch}' not found in database!\n";
        continue;
    }
    
    echo "Branch found - ID: {$branch->id}, Name: {$branch->name}\n";
    
    // Test the query that getManagerTickets uses
    $ticketsInBranch = Ticket::where('branch_id', $branch->id)->count();
    echo "Tickets in this branch: {$ticketsInBranch}\n";
    
    if ($ticketsInBranch > 0) {
        $sampleTickets = Ticket::where('branch_id', $branch->id)->with(['status', 'customer'])->limit(3)->get();
        echo "Sample tickets:\n";
        foreach ($sampleTickets as $ticket) {
            echo "  - {$ticket->ticket_id}: {$ticket->title} (Status: {$ticket->status->name})\n";
        }
    }
    
    // Test the actual API call
    echo "\nTesting API call...\n";
    $request = Request::create('/api/v1/manager/tickets', 'GET');
    $request->setUserResolver(function () use ($manager) {
        return $manager;
    });
    
    try {
        $controller = new \App\Http\Controllers\Api\TicketController();
        $response = $controller->getManagerTickets($request);
        $data = json_decode($response->getContent(), true);
        
        if ($data['success']) {
            echo "✅ API call successful - returned " . count($data['tickets']) . " tickets\n";
            if (!empty($data['tickets'])) {
                echo "First ticket: {$data['tickets'][0]['ticket_id']} - {$data['tickets'][0]['title']}\n";
            }
        } else {
            echo "❌ API call failed: " . ($data['message'] ?? 'Unknown error') . "\n";
        }
    } catch (Exception $e) {
        echo "❌ API call threw exception: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Debug Complete ===\n";