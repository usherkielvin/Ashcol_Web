<?php
/**
 * Test if profile photo URLs are accessible
 */

$testUrl = 'http://10.0.2.2:8000/storage/profile_photos/profile_18_1770827834.jpg';

echo "Testing image URL accessibility...\n";
echo "URL: {$testUrl}\n\n";

// Check if file exists locally
$localPath = __DIR__ . '/public/storage/profile_photos/profile_18_1770827834.jpg';
echo "Local file path: {$localPath}\n";
echo "File exists locally: " . (file_exists($localPath) ? 'YES' : 'NO') . "\n";

if (file_exists($localPath)) {
    echo "File size: " . number_format(filesize($localPath)) . " bytes\n";
}

echo "\nTo test if accessible via HTTP:\n";
echo "1. Make sure Laravel server is running: php artisan serve --host=0.0.0.0 --port=8000\n";
echo "2. Open this URL in browser: {$testUrl}\n";
echo "3. Or use curl: curl -I {$testUrl}\n";
