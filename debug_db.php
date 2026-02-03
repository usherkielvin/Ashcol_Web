<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "database.default: " . config('database.default') . PHP_EOL;
echo "resolved database name: " . config('database.connections.' . config('database.default') . '.database') . PHP_EOL;
echo "env DB_CONNECTION: " . env('DB_CONNECTION') . PHP_EOL;
echo "env DB_DATABASE: " . env('DB_DATABASE') . PHP_EOL;

// quick DB connection test
try {
    $pdo = DB::connection()->getPdo();
    echo "DB connection: OK\n";
} catch (Exception $e) {
    echo "DB connection: FAILED - " . $e->getMessage() . PHP_EOL;
}

return 0;
