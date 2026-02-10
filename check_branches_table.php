<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$columns = \Illuminate\Support\Facades\Schema::getColumnListing('branches');
echo "Branches table columns:\n";
foreach ($columns as $column) {
    echo "- " . $column . "\n";
}