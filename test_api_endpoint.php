<?php
// Simple test to verify the API endpoint
require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Api\TicketController;

echo "Testing Manager Tickets API Endpoint...\n\n";

// Get a manager user for testing
$manager = \App\Models\User::where('role', 'manager')->first();

if (!$manager) {
    echo "❌ No manager found in database\n";
    exit(1);
}

echo "✅ Manager found: " . $manager->email . " (Branch: " . $manager->branch . ")\n";

// Create a mock request with authentication
$request = Request::create('/api/v1/manager/tickets', 'GET');
$request->setUserResolver(function () use ($manager) {
    return $manager;
});

// Set a dummy authorization header (the middleware will use the user resolver)
$request->headers->set('Authorization', 'Bearer test-token');

try {
    // Call the controller method directly
    $controller = new TicketController();
    $response = $controller->getManagerTickets($request);
    
    echo "✅ API call completed\n";
    echo "✅ Response status: " . $response->getStatusCode() . "\n";
    
    $content = $response->getContent();
    echo "✅ Response content length: " . strlen($content) . " characters\n";
    
    // Try to parse JSON
    $data = json_decode($content, true);
    if ($data && isset($data['success'])) {
        echo "✅ JSON parsing successful\n";
        echo "✅ Success: " . ($data['success'] ? 'true' : 'false') . "\n";
        
        if ($data['success'] && isset($data['tickets'])) {
            echo "✅ Tickets found: " . count($data['tickets']) . "\n";
            
            if (!empty($data['tickets'])) {
                $ticket = $data['tickets'][0];
                echo "✅ Sample ticket:\n";
                echo "  - Ticket ID: " . ($ticket['ticket_id'] ?? 'N/A') . "\n";
                echo "  - Title: " . ($ticket['title'] ?? 'N/A') . "\n";
                echo "  - Status: " . ($ticket['status'] ?? 'N/A') . "\n";
                echo "  - Assigned Staff: " . ($ticket['assigned_staff'] ?? 'null') . "\n";
                echo "  - Branch: " . ($ticket['branch'] ?? 'null') . "\n";
                
                if ($ticket['assigned_staff'] !== null) {
                    echo "✅ Assigned staff field is properly populated (FIXED!)\n";
                } else {
                    echo "❌ Assigned staff field is still null (BUG PERSISTS)\n";
                }
                
                if ($ticket['branch'] !== null) {
                    echo "✅ Branch field is properly populated (FIXED!)\n";
                } else {
                    echo "❌ Branch field is still null (BUG PERSISTS)\n";
                }
            }
        } elseif (isset($data['message'])) {
            echo "❌ API Error: " . $data['message'] . "\n";
        }
    } else {
        echo "❌ Invalid JSON response\n";
        echo "Response content: " . substr($content, 0, 200) . "...\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception occurred: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\nTest completed.\n";