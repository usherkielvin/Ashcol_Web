<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TicketStatus;

echo "Checking ticket statuses...\n";

$statuses = TicketStatus::all();

if ($statuses->count() > 0) {
    echo "Found " . $statuses->count() . " ticket statuses:\n";
    foreach ($statuses as $status) {
        echo "- {$status->name} (ID: {$status->id}, Default: " . ($status->is_default ? 'Yes' : 'No') . ")\n";
    }
} else {
    echo "No ticket statuses found in database!\n";
}

$defaultStatus = TicketStatus::getDefault();
if ($defaultStatus) {
    echo "\nDefault status: {$defaultStatus->name}\n";
} else {
    echo "\nNo default status found!\n";
}