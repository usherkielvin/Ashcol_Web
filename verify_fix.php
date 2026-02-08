<?php
require_once 'bootstrap/app.php';

// Test manager tickets API
echo "Testing Manager Tickets API Fix...\n\n";

// Get a manager user
$manager = \App\Models\User::where('role', 'manager')->first();

if (!$manager) {
    echo "❌ No manager found in database\n";
    exit(1);
}

echo "✅ Manager found: " . $manager->email . " (Branch: " . $manager->branch . ")\n";

// Create a mock request
$request = new \Illuminate\Http\Request();
$request->setUserResolver(function () use ($manager) {
    return $manager;
});

// Call the controller method
$controller = new \App\Http\Controllers\Api\TicketController();
$response = $controller->getManagerTickets($request);

// Check response
if ($response->getStatusCode() === 200) {
    $data = json_decode($response->getContent(), true);
    if ($data['success']) {
        echo "✅ API call successful\n";
        echo "✅ Tickets found: " . count($data['tickets']) . "\n";
        
        if (!empty($data['tickets'])) {
            $ticket = $data['tickets'][0];
            echo "✅ Sample ticket data:\n";
            echo "  - ID: " . ($ticket['ticket_id'] ?? 'N/A') . "\n";
            echo "  - Title: " . ($ticket['title'] ?? 'N/A') . "\n";
            echo "  - Status: " . ($ticket['status'] ?? 'N/A') . "\n";
            echo "  - Customer: " . ($ticket['customer_name'] ?? 'N/A') . "\n";
            echo "  - Assigned Staff: " . ($ticket['assigned_staff'] ?? 'null') . "\n";
            echo "  - Branch: " . ($ticket['branch'] ?? 'null') . "\n";
            
            if ($ticket['assigned_staff'] !== null) {
                echo "✅ Assigned staff field is properly populated\n";
            } else {
                echo "⚠️  Assigned staff field is null (this was the bug)\n";
            }
            
            if ($ticket['branch'] !== null) {
                echo "✅ Branch field is properly populated\n";
            } else {
                echo "⚠️  Branch field is null (this was the bug)\n";
            }
        }
    } else {
        echo "❌ API returned success=false: " . $data['message'] . "\n";
    }
} else {
    echo "❌ HTTP Error: " . $response->getStatusCode() . "\n";
    echo "Response: " . $response->getContent() . "\n";
}

echo "\nFix verification completed.\n";