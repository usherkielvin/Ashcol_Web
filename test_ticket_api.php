<?php

// Test the API ticket creation endpoint with a valid token
$baseUrl = 'http://127.0.0.1:8000/api/v1';
$token = '29|AcfY7AWaXSMu3bjVZReQYlOjzNgQHMjJBdDWYBYQ176bcc4e'; // From previous script

echo "=== Testing Ticket Creation API ===\n";

// Test 1: JSON request (like Android app might send)
echo "Test 1: JSON Request\n";
$ticketData = [
    'title' => 'Test Service Request JSON',
    'description' => 'This is a test ticket from API using JSON',
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

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response (HTTP $httpCode):\n";
echo $response . "\n\n";

// Test 2: Form data request (multipart/form-data)
echo "Test 2: Form Data Request\n";

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

$formResponse = curl_exec($ch);
$formHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Form Response (HTTP $formHttpCode):\n";
echo $formResponse . "\n\n";

// Test 3: Minimal required fields only
echo "Test 3: Minimal Required Fields\n";

$minimalData = [
    'description' => 'Minimal test ticket',
    'address' => '789 Minimal Street',
    'contact' => '09999999999',
    'service_type' => 'General'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/tickets');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($minimalData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$minimalResponse = curl_exec($ch);
$minimalHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Minimal Response (HTTP $minimalHttpCode):\n";
echo $minimalResponse . "\n\n";

echo "=== Check Laravel Logs ===\n";
echo "Check storage/logs/laravel.log for detailed error information.\n";