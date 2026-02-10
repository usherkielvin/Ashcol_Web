<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$exists = \Illuminate\Support\Facades\Schema::hasTable('cache');
echo "Cache table exists: " . ($exists ? 'Yes' : 'No') . "\n";

if (!$exists) {
    echo "Creating cache table...\n";
    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'database/migrations/0001_01_01_000001_create_cache_table.php',
        '--force' => true
    ]);
    echo "Cache table created\n";
}