<?php
// Test script to verify the manager tickets fix
require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;

$app = new Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$app->bootstrap();

// Test the getManagerTickets method
echo "Testing Manager Tickets Fix...\n\n";

// Get a manager user for testing
$manager = \App\Models\User::where('role', 'manager')->first();

if (!$manager) {
    echo "❌ No manager found in database\n";
    exit(1);
}

echo "✅ Manager found: " . $manager->email . "\n";
echo "✅ Branch: " . $manager->branch . "\n";

// Create a mock request with the manager user
$request = new \Illuminate\Http\Request();
$request->setUserResolver(function () use ($manager) {
    return $manager;
});

// Call the controller method
$controller = new \App\Http\Controllers\Api\TicketController();
$response = $controller->getManagerTickets($request);

// Parse the response
$data = json_decode($response->getContent(), true);

if ($data['success']) {
    echo "✅ API call successful\n";
    echo "✅ Tickets found: " . count($data['tickets']) . "\n";
    
    if (count($data['tickets']) > 0) {
        $firstTicket = $data['tickets'][0];
        echo "✅ First ticket data:\n";
        echo "  - Ticket ID: " . ($firstTicket['ticket_id'] ?? 'N/A') . "\n";
        echo "  - Title: " . ($firstTicket['title'] ?? 'N/A') . "\n";
        echo "  - Status: " . ($firstTicket['status'] ?? 'N/A') . "\n";
        echo "  - Customer: " . ($firstTicket['customer_name'] ?? 'N/A') . "\n";
        echo "  - Assigned Staff: " . ($firstTicket['assigned_staff'] ?? 'N/A') . "\n";
        echo "  - Branch: " . ($firstTicket['branch'] ?? 'N/A') . "\n";
        
        // Check if assigned_staff and branch are properly populated
        if ($firstTicket['assigned_staff'] !== null) {
            echo "✅ Assigned staff field is populated\n";
        } else {
            echo "⚠️  Assigned staff field is null\n";
        }
        
        if ($firstTicket['branch'] !== null) {
            echo "✅ Branch field is populated\n";
        } else {
            echo "⚠️  Branch field is null\n";
        }
    }
} else {
    echo "❌ API call failed: " . $data['message'] . "\n";
}

echo "\nTest completed.\n";