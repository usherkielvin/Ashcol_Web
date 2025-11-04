<?php
/**
 * Laravel Autoload Test File
 * This file tests if Laravel's autoloader is working correctly
 * 
 * Access via:
 * - php -S localhost:8000 (then visit http://localhost:8000/test_autoload.php)
 * - XAMPP: http://localhost/ashcol_portal/test_autoload.php
 */

// Include Composer autoload
require __DIR__ . '/vendor/autoload.php';

// Detect if running from CLI or web browser
$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Laravel Autoload Test</title>';
    echo '<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;background:#f5f5f5;}';
    echo '.container{background:white;padding:30px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}';
    echo 'h1{color:#333;border-bottom:3px solid #6366f1;padding-bottom:10px;}';
    echo '.test{margin:15px 0;padding:10px;background:#f9fafb;border-left:4px solid #ccc;border-radius:4px;}';
    echo '.pass{color:#10b981;border-left-color:#10b981;}';
    echo '.fail{color:#ef4444;border-left-color:#ef4444;background:#fee;}';
    echo '.warning{color:#f59e0b;border-left-color:#f59e0b;}';
    echo '.summary{background:#e0e7ff;padding:15px;border-radius:4px;margin-top:20px;}';
    echo '</style></head><body><div class="container">';
    echo '<h1>Laravel Autoload Test</h1>';
}

$tests = [];
$allPassed = true;

// Test 1: Check if Composer autoload file exists
$testName = "Test 1: Checking Composer autoload file";
if (!$isCli) echo '<div class="test pass">';
if (!$isCli) echo '<strong>✓ ' . $testName . '</strong><br>';
if ($isCli) echo "=== Laravel Autoload Test ===\n\n";
if ($isCli) echo "$testName...\n";

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    $tests[] = ['name' => $testName, 'status' => 'pass', 'message' => 'Composer autoloader loaded successfully'];
    if ($isCli) echo "✓ Composer autoload file found and loaded\n\n";
} else {
    $tests[] = ['name' => $testName, 'status' => 'fail', 'message' => 'Composer autoload file not found! Run: composer install'];
    if (!$isCli) echo '<div class="test fail"><strong>✗ ' . $testName . '</strong><br>';
    if ($isCli) echo "✗ ERROR: Composer autoload file not found!\n";
    if ($isCli) echo "  Run: composer install\n\n";
    $allPassed = false;
    if (!$isCli) echo '</div>';
    if (!$isCli) {
        echo '</div></body></html>';
        exit;
    }
    exit(1);
}

// Test 2: Check if Laravel classes can be autoloaded
$testName = "Test 2: Testing Laravel framework classes";
if (!$isCli) echo '<div class="test">';
if (!$isCli) echo '<strong>' . $testName . '</strong><br>';
if ($isCli) echo "$testName...\n";

try {
    if (class_exists('Illuminate\Foundation\Application')) {
        if ($isCli) echo "✓ Illuminate\\Foundation\\Application class loaded\n";
        if (!$isCli) echo '✓ Illuminate\Foundation\Application class loaded<br>';
    } else {
        $tests[] = ['name' => $testName, 'status' => 'fail', 'message' => 'Laravel Application class not found'];
        if ($isCli) echo "✗ ERROR: Laravel Application class not found\n\n";
        if (!$isCli) echo '<div class="test fail"><strong>✗ ' . $testName . '</strong><br>ERROR: Laravel Application class not found</div>';
        $allPassed = false;
        if ($isCli) exit(1);
    }
    
    if (class_exists('Illuminate\Support\Facades\Facade')) {
        if ($isCli) echo "✓ Illuminate\\Support\\Facades\\Facade class loaded\n";
        if (!$isCli) echo '✓ Illuminate\Support\Facades\Facade class loaded<br>';
        $tests[] = ['name' => $testName, 'status' => 'pass', 'message' => 'Laravel framework classes loaded successfully'];
    } else {
        $tests[] = ['name' => $testName, 'status' => 'fail', 'message' => 'Laravel Facade class not found'];
        if ($isCli) echo "✗ ERROR: Laravel Facade class not found\n\n";
        if (!$isCli) echo '<div class="test fail"><strong>✗ ' . $testName . '</strong><br>ERROR: Laravel Facade class not found</div>';
        $allPassed = false;
        if ($isCli) exit(1);
    }
} catch (Exception $e) {
    $tests[] = ['name' => $testName, 'status' => 'fail', 'message' => $e->getMessage()];
    if ($isCli) echo "✗ ERROR: " . $e->getMessage() . "\n\n";
    if (!$isCli) echo '<div class="test fail"><strong>✗ ' . $testName . '</strong><br>ERROR: ' . htmlspecialchars($e->getMessage()) . '</div>';
    $allPassed = false;
    if ($isCli) exit(1);
}

if (!$isCli) echo '</div>';

// Test 3: Check if App namespace classes can be autoloaded
$testName = "Test 3: Testing App namespace autoloading";
if (!$isCli) echo '<div class="test">';
if (!$isCli) echo '<strong>' . $testName . '</strong><br>';
if ($isCli) echo "\n$testName...\n";

try {
    if (class_exists('App\Providers\AppServiceProvider')) {
        $tests[] = ['name' => $testName, 'status' => 'pass', 'message' => 'App namespace classes loaded successfully'];
        if ($isCli) echo "✓ App\\Providers\\AppServiceProvider class loaded\n";
        if (!$isCli) echo '✓ App\Providers\AppServiceProvider class loaded<br>';
    } else {
        $tests[] = ['name' => $testName, 'status' => 'fail', 'message' => 'AppServiceProvider class not found'];
        if ($isCli) echo "✗ ERROR: AppServiceProvider class not found\n\n";
        if (!$isCli) echo '<div class="test fail"><strong>✗ ' . $testName . '</strong><br>ERROR: AppServiceProvider class not found</div>';
        $allPassed = false;
        if ($isCli) exit(1);
    }
} catch (Exception $e) {
    $tests[] = ['name' => $testName, 'status' => 'fail', 'message' => $e->getMessage()];
    if ($isCli) echo "✗ ERROR: " . $e->getMessage() . "\n\n";
    if (!$isCli) echo '<div class="test fail"><strong>✗ ' . $testName . '</strong><br>ERROR: ' . htmlspecialchars($e->getMessage()) . '</div>';
    $allPassed = false;
    if ($isCli) exit(1);
}

if (!$isCli) echo '</div>';

// Test 4: Check autoloader configuration
$testName = "Test 4: Checking autoloader configuration";
if (!$isCli) echo '<div class="test">';
if (!$isCli) echo '<strong>' . $testName . '</strong><br>';
if ($isCli) echo "\n$testName...\n";

$composerAutoloader = require $autoloadPath;
$prefixes = $composerAutoloader->getPrefixesPsr4();
if (isset($prefixes['App\\'])) {
    $tests[] = ['name' => $testName, 'status' => 'pass', 'message' => 'App namespace is registered in autoloader'];
    if ($isCli) echo "✓ App\\ namespace is registered in autoloader\n";
    if ($isCli) echo "  Path: " . $prefixes['App\\'][0] . "\n";
    if (!$isCli) echo '✓ App\ namespace is registered in autoloader<br>';
    if (!$isCli) echo 'Path: ' . htmlspecialchars($prefixes['App\\'][0]) . '<br>';
} else {
    $tests[] = ['name' => $testName, 'status' => 'warning', 'message' => 'App namespace not found in autoloader'];
    if ($isCli) echo "✗ WARNING: App\\ namespace not found in autoloader\n";
    if (!$isCli) echo '<span class="warning">⚠ WARNING: App\ namespace not found in autoloader</span><br>';
}

if (!$isCli) echo '</div>';

// Test 5: Test if bootstrap file exists
$testName = "Test 5: Testing Laravel bootstrap";
if (!$isCli) echo '<div class="test">';
if (!$isCli) echo '<strong>' . $testName . '</strong><br>';
if ($isCli) echo "\n$testName...\n";

try {
    if (file_exists(__DIR__ . '/bootstrap/app.php')) {
        $tests[] = ['name' => $testName, 'status' => 'pass', 'message' => 'Bootstrap file exists'];
        if ($isCli) echo "✓ Bootstrap file exists\n";
        if (!$isCli) echo '✓ Bootstrap file exists<br>';
    } else {
        $tests[] = ['name' => $testName, 'status' => 'fail', 'message' => 'Bootstrap file not found'];
        if ($isCli) echo "✗ ERROR: Bootstrap file not found\n\n";
        if (!$isCli) echo '<div class="test fail"><strong>✗ ' . $testName . '</strong><br>ERROR: Bootstrap file not found</div>';
        $allPassed = false;
        if ($isCli) exit(1);
    }
} catch (Exception $e) {
    $tests[] = ['name' => $testName, 'status' => 'fail', 'message' => $e->getMessage()];
    if ($isCli) echo "✗ ERROR: " . $e->getMessage() . "\n\n";
    if (!$isCli) echo '<div class="test fail"><strong>✗ ' . $testName . '</strong><br>ERROR: ' . htmlspecialchars($e->getMessage()) . '</div>';
    $allPassed = false;
    if ($isCli) exit(1);
}

if (!$isCli) echo '</div>';

// Summary
if (!$isCli) {
    echo '<div class="summary">';
    if ($allPassed) {
        echo '<h2 style="color:#10b981;margin:0;">✓ All Tests Passed!</h2>';
        echo '<p>Laravel autoloader is working correctly.</p>';
    } else {
        echo '<h2 style="color:#ef4444;margin:0;">✗ Some Tests Failed</h2>';
        echo '<p>Please check the errors above and fix any issues.</p>';
    }
    echo '</div>';
    echo '</div></body></html>';
} else {
    echo "\n";
    if ($allPassed) {
        echo "=== All Tests Passed! ===\n";
        echo "Laravel autoloader is working correctly.\n";
    } else {
        echo "=== Some Tests Failed ===\n";
        echo "Please check the errors above and fix any issues.\n";
    }
}
