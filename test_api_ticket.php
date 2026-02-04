<?php

// Simple test to simulate the API ticket creation request
$url = 'http://localhost:8000/api/v1/tickets';

// Test data (similar to what the Android app would send)
$data = [
    'title' => 'Test Ticket',
    'description' => 'This is a test ticket from PHP script',
    'service_type' => 'General Service',
    'address' => 'Test Address, Manila',
    'contact' => '09123456789',
    'preferred_date' => '2026-02-10'
];

// You'll need a valid token - let's first check if the server is running
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/v1/test');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

echo "Testing if Laravel server is running...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ Server connection failed: $error\n";
    echo "Make sure Laravel development server is running with: php artisan serve\n";
} else {
    echo "✅ Server responded with HTTP code: $httpCode\n";
    if ($response) {
        echo "Response: $response\n";
    }
}

echo "\nTo test ticket creation, you need to:\n";
echo "1. Start Laravel server: php artisan serve\n";
echo "2. Create a test user and get an auth token\n";
echo "3. Use that token to make authenticated requests to /api/v1/tickets\n";