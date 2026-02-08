<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Api\TicketController;

// Get the manager
$manager = \App\Models\User::where('role', 'manager')->first();
echo "Manager: " . $manager->email . " (Branch: " . $manager->branch . ")\n";

// Get the branch ID for this manager
$branch = \App\Models\Branch::where('name', $manager->branch)->first();
echo "Manager's branch ID: " . $branch->id . "\n";

// Check what tickets exist for this branch
$ticketsInBranch = \App\Models\Ticket::where('branch_id', $branch->id)->get();
echo "Total tickets in this branch: " . $ticketsInBranch->count() . "\n";

foreach($ticketsInBranch as $ticket) {
    echo "Ticket " . $ticket->ticket_id . ":\n";
    echo "  - Title: " . $ticket->title . "\n";
    echo "  - Status: " . $ticket->status->name . "\n";
    echo "  - Assigned Staff ID: " . $ticket->assigned_staff_id . "\n";
    if ($ticket->assignedStaff) {
        echo "  - Assigned Staff Name: " . $ticket->assignedStaff->firstName . " " . $ticket->assignedStaff->lastName . "\n";
    } else {
        echo "  - No assigned staff\n";
    }
    echo "\n";
}

// Now test the API call
$request = Request::create('/api/v1/manager/tickets', 'GET');
$request->setUserResolver(function () use ($manager) {
    return $manager;
});
$request->headers->set('Authorization', 'Bearer test-token');

$controller = new TicketController();
$response = $controller->getManagerTickets($request);

$data = json_decode($response->getContent(), true);
if ($data && $data['success']) {
    echo "API returned " . count($data['tickets']) . " tickets:\n";
    foreach($data['tickets'] as $ticket) {
        echo "  - " . $ticket['ticket_id'] . " (" . $ticket['title'] . ") - Staff: " . ($ticket['assigned_staff'] ?? 'null') . "\n";
    }
}