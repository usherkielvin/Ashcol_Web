<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing API Authentication and Tickets ===\n";

// Find the customer user
$customer = \App\Models\User::where('role', 'customer')->first();
if (!$customer) {
    echo "No customer found! Creating a test customer...\n";
    
    $customer = \App\Models\User::create([
        'firstName' => 'Test',
        'lastName' => 'Customer',
        'email' => 'test.customer@example.com',
        'password' => bcrypt('password'),
        'role' => 'customer',
        'phone' => '1234567890',
    ]);
    
    echo "Test customer created with ID: " . $customer->id . "\n";
}

echo "Customer found: " . $customer->firstName . " " . $customer->lastName . " (ID: " . $customer->id . ")\n";
echo "Customer role: " . $customer->role . "\n";
echo "Is customer: " . ($customer->isCustomer() ? 'Yes' : 'No') . "\n";

// Check if customer has any tickets
$existingTickets = \App\Models\Ticket::where('customer_id', $customer->id)->count();
echo "Existing tickets for customer: " . $existingTickets . "\n";

// Create a test ticket if none exist
if ($existingTickets == 0) {
    echo "Creating a test ticket...\n";
    
    $status = \App\Models\TicketStatus::where('name', 'Pending')->first();
    if (!$status) {
        $status = \App\Models\TicketStatus::create([
            'name' => 'Pending',
            'color' => '#FFA500'
        ]);
    }
    
    $ticket = \App\Models\Ticket::create([
        'ticket_id' => 'TEST_' . time(),
        'title' => 'Test Service Request',
        'description' => 'This is a test ticket for debugging',
        'service_type' => 'Plumbing',
        'address' => '123 Test Street',
        'contact' => $customer->phone,
        'priority' => 'medium',
        'customer_id' => $customer->id,
        'status_id' => $status->id,
    ]);
    
    echo "Test ticket created: " . $ticket->ticket_id . "\n";
}

// Create a token for the customer
$token = $customer->createToken('test-token')->plainTextToken;
echo "Token created: " . substr($token, 0, 20) . "...\n";

// Test the API endpoint
$request = \Illuminate\Http\Request::create('/api/v1/tickets', 'GET');
$request->headers->set('Authorization', 'Bearer ' . $token);

// Manually authenticate the user for this request
$user = \Laravel\Sanctum\PersonalAccessToken::findToken($token)?->tokenable;
if ($user) {
    echo "User authenticated via token: " . $user->firstName . " " . $user->lastName . "\n";
    echo "User role: " . $user->role . "\n";
    echo "Is customer: " . ($user->isCustomer() ? 'Yes' : 'No') . "\n";
    
    // Test the controller method directly
    $controller = new \App\Http\Controllers\Api\TicketController();
    $request->setUserResolver(function () use ($user) {
        return $user;
    });
    
    $response = $controller->index($request);
    $data = json_decode($response->getContent(), true);
    
    echo "\n=== API Response ===\n";
    echo "Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
    echo "Tickets count: " . count($data['tickets']) . "\n";
    
    if (!empty($data['tickets'])) {
        echo "\nTickets:\n";
        foreach ($data['tickets'] as $ticket) {
            echo "- ID: " . $ticket['ticket_id'] . "\n";
            echo "  Title: " . $ticket['title'] . "\n";
            echo "  Status: " . $ticket['status'] . "\n";
            echo "  Service: " . $ticket['service_type'] . "\n";
            echo "  Created: " . $ticket['created_at'] . "\n";
            echo "  ---\n";
        }
    } else {
        echo "No tickets found in response\n";
    }
    
    // Also test the raw JSON response
    echo "\n=== Raw JSON Response ===\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    
} else {
    echo "Failed to authenticate user with token\n";
}

echo "\n=== Database Check ===\n";
$totalTickets = \App\Models\Ticket::count();
$customerTickets = \App\Models\Ticket::where('customer_id', $customer->id)->count();
echo "Total tickets in database: " . $totalTickets . "\n";
echo "Tickets for this customer: " . $customerTickets . "\n";