<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Query branches
echo "=== BRANCHES ===\n";
$branches = \App\Models\Branch::all();
foreach ($branches as $branch) {
    echo "ID: {$branch->id}, Name: '{$branch->name}'\n";
}

// Query managers
echo "\n=== MANAGERS ===\n";
$managers = \App\Models\User::where('role', 'manager')->get();
foreach ($managers as $manager) {
    echo "ID: {$manager->id}, Email: {$manager->email}, Branch: '{$manager->branch}'\n";
}

// Query tickets
echo "\n=== TICKETS ===\n";
$tickets = \App\Models\Ticket::with('branch')->get();
foreach ($tickets as $ticket) {
    $branchName = $ticket->branch ? $ticket->branch->name : 'NULL';
    echo "Ticket: {$ticket->ticket_id}, Branch ID: {$ticket->branch_id}, Branch Name: '{$branchName}', Customer: {$ticket->customer_id}\n";
}

// Check for Cavite specifically
echo "\n=== CAVITE BRANCH ANALYSIS ===\n";
$caviteBranch = \App\Models\Branch::where('name', 'Cavite')->first();
if ($caviteBranch) {
    echo "Cavite Branch ID: {$caviteBranch->id}\n";
    $caviteTickets = \App\Models\Ticket::where('branch_id', $caviteBranch->id)->count();
    echo "Tickets with Cavite branch_id: {$caviteTickets}\n";
} else {
    echo "No branch named 'Cavite' found!\n";
    echo "Trying case-insensitive search...\n";
    $caviteBranch = \App\Models\Branch::whereRaw('LOWER(name) = ?', ['cavite'])->first();
    if ($caviteBranch) {
        echo "Found branch with name: '{$caviteBranch->name}' (ID: {$caviteBranch->id})\n";
        $caviteTickets = \App\Models\Ticket::where('branch_id', $caviteBranch->id)->count();
        echo "Tickets with this branch_id: {$caviteTickets}\n";
    }
}

$caviteManager = \App\Models\User::where('role', 'manager')->where('branch', 'Cavite')->first();
if ($caviteManager) {
    echo "\nCavite Manager: {$caviteManager->email}, Branch field: '{$caviteManager->branch}'\n";
} else {
    echo "\nNo manager with branch='Cavite' found!\n";
    echo "Trying case-insensitive search...\n";
    $caviteManager = \App\Models\User::where('role', 'manager')->whereRaw('LOWER(branch) = ?', ['cavite'])->first();
    if ($caviteManager) {
        echo "Found manager with branch: '{$caviteManager->branch}'\n";
    }
}
