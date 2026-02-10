<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$columns = \Illuminate\Support\Facades\Schema::getColumnListing('users');
echo "Users table columns:\n";
foreach ($columns as $column) {
    echo "- " . $column . "\n";
}