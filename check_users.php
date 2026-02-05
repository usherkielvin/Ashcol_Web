<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Users in Database ===\n";
$users = \App\Models\User::all();
foreach ($users as $user) {
    echo "ID: {$user->id}, Email: {$user->email}, Role: {$user->role}\n";
}

echo "\n=== Ticket Statuses ===\n";
$statuses = \App\Models\TicketStatus::all();
foreach ($statuses as $status) {
    echo "ID: {$status->id}, Name: {$status->name}, Default: " . ($status->is_default ? 'Yes' : 'No') . "\n";
}

echo "\n=== Branches ===\n";
$branches = \App\Models\Branch::all();
foreach ($branches as $branch) {
    echo "ID: {$branch->id}, Name: {$branch->name}, Active: " . ($branch->is_active ? 'Yes' : 'No') . "\n";
}