<?php
// Script to set up managers and assign tickets to branches
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Branch;
use App\Models\Ticket;

echo "=== Setting up managers and assigning tickets ===\n\n";

// 1. Create managers for some branches
echo "1. Creating managers...\n";

// Get some branches
$branches = Branch::limit(3)->get();

$managerEmails = ['manager1@ashcol.com', 'manager2@ashcol.com', 'manager3@ashcol.com'];
$managerNames = [
    ['John', 'Manager'],
    ['Jane', 'Supervisor'], 
    ['Bob', 'Director']
];

foreach ($branches as $index => $branch) {
    $email = $managerEmails[$index] ?? "manager" . ($index + 1) . "@ashcol.com";
    $firstName = $managerNames[$index][0] ?? "Manager";
    $lastName = $managerNames[$index][1] ?? "User";
    
    // Check if manager already exists
    $existingManager = User::where('email', $email)->first();
    if ($existingManager) {
        echo "Manager {$email} already exists\n";
        $manager = $existingManager;
    } else {
        $manager = User::create([
            'username' => strtolower($firstName . '.' . $lastName),
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'password' => \Illuminate\Support\Facades\Hash::make('Manager123'),
            'role' => 'manager',
            'branch' => $branch->name,
            'email_verified_at' => now(),
        ]);
        echo "Created manager: {$firstName} {$lastName} ({$email}) for branch: {$branch->name}\n";
    }
    
    // Note: Branch table doesn't have manager column, manager branch is stored in user table
}

echo "\n2. Assigning tickets to branches...\n";

// Get all tickets and branches
$tickets = Ticket::all();
$branches = Branch::all();

if ($tickets->count() > 0 && $branches->count() > 0) {
    foreach ($tickets as $index => $ticket) {
        // Assign to a branch (round-robin)
        $branch = $branches[$index % $branches->count()];
        $ticket->branch_id = $branch->id;
        $ticket->save();
        echo "Assigned ticket {$ticket->ticket_id} to branch: {$branch->name}\n";
    }
} else {
    echo "No tickets or branches to assign\n";
}

echo "\n3. Verifying setup...\n";

// Check managers
$managers = User::where('role', 'manager')->get();
echo "Total managers: " . $managers->count() . "\n";
foreach ($managers as $manager) {
    echo "  - {$manager->firstName} {$manager->lastName} ({$manager->email}) - Branch: {$manager->branch}\n";
}

// Check tickets with branches
$tickets = Ticket::with('branch')->get();
echo "\nTotal tickets: " . $tickets->count() . "\n";
foreach ($tickets as $ticket) {
    echo "  - {$ticket->ticket_id}: {$ticket->title} - Branch: " . ($ticket->branch ? $ticket->branch->name : 'None') . "\n";
}

echo "\n=== Setup Complete ===\n";