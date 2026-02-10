<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
echo "Tables in database:\n";
foreach ($tables as $table) {
    $tableName = array_values((array)$table)[0];
    echo "- " . $tableName . "\n";
}