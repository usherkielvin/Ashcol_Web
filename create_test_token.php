<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get a customer user and create a token for testing
$user = \App\Models\User::where('role', 'customer')->first();

if (!$user) {
    echo "No customer user found\n";
    exit(1);
}

echo "Using user: {$user->email} (ID: {$user->id})\n";

// Create a token for this user
$token = $user->createToken('test-token')->plainTextToken;

echo "Token created: {$token}\n";
echo "User ID: {$user->id}\n";
echo "User Email: {$user->email}\n";
echo "User Role: {$user->role}\n";
echo "User Branch: " . ($user->branch ?? 'None') . "\n";
echo "User Location: " . ($user->location ?? 'None') . "\n";