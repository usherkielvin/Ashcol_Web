<?php

require_once 'vendor/autoload.php';

// Test the API ticket creation endpoint
$baseUrl = 'http://127.0.0.1:8000/api/v1';

// First, let's get a user token by logging in
echo "=== Testing API Ticket Creation ===\n";

// Get a test user
$loginData = [
    'email' => 'customer@example.com', // Assuming this user exists
    'password' => 'password123'
];

// Try to login
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$loginResponse = curl_exec($ch);
$loginHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Login Response (HTTP $loginHttpCode):\n";
echo $loginResponse . "\n\n";

$loginData = json_decode($loginResponse, true);

if ($loginHttpCode !== 200 || !isset($loginData['token'])) {
    echo "Login failed. Let's try to create a test user first.\n";
    
    // Try to register a test user
    $registerData = [
        'name' => 'Test Customer',
        'email' => 'testcustomer@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'phone' => '1234567890'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/register');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($registerData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $registerResponse = curl_exec($ch);
    $registerHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Register Response (HTTP $registerHttpCode):\n";
    echo $registerResponse . "\n\n";
    
    $registerData = json_decode($registerResponse, true);
    
    if ($registerHttpCode === 201 && isset($registerData['token'])) {
        $token = $registerData['token'];
        echo "Successfully registered and got token: " . substr($token, 0, 20) . "...\n\n";
    } else {
        echo "Registration failed. Exiting.\n";
        exit(1);
    }
} else {
    $token = $loginData['token'];
    echo "Successfully logged in. Token: " . substr($token, 0, 20) . "...\n\n";
}

// Now test ticket creation
echo "=== Creating Ticket ===\n";

$ticketData = [
    'title' => 'Test Service Request',
    'description' => 'This is a test ticket from API',
    'address' => '123 Test Street, Manila',
    'contact' => '09123456789',
    'service_type' => 'Plumbing',
    'preferred_date' => '2026-02-10'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/tickets');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($ticketData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$ticketResponse = curl_exec($ch);
$ticketHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Ticket Creation Response (HTTP $ticketHttpCode):\n";
echo $ticketResponse . "\n\n";

// Also test with multipart/form-data (like Android app might send)
echo "=== Creating Ticket with Form Data ===\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/tickets');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'title' => 'Test Service Request Form',
    'description' => 'This is a test ticket from API using form data',
    'address' => '456 Form Street, Manila',
    'contact' => '09123456789',
    'service_type' => 'Electrical',
    'preferred_date' => '2026-02-11'
]);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$formTicketResponse = curl_exec($ch);
$formTicketHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Form Ticket Creation Response (HTTP $formTicketHttpCode):\n";
echo $formTicketResponse . "\n\n";

echo "=== Debug Info ===\n";
echo "Check Laravel logs for more details.\n";