<?php
/**
 * Comprehensive test for profile photo sync
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== PROFILE PHOTO SYNC TEST ===\n\n";

// 1. Check database schema
echo "1. Checking database schema...\n";
$columns = DB::select("SHOW COLUMNS FROM users LIKE 'profile_photo'");
if (empty($columns)) {
    echo "   ERROR: profile_photo column does NOT exist!\n\n";
} else {
    echo "   OK: profile_photo column exists\n";
    echo "   Type: " . $columns[0]->Type . "\n";
    echo "   Null: " . $columns[0]->Null . "\n";
    echo "   Default: " . $columns[0]->Default . "\n\n";
}

// 2. Check users with profile photos
echo "2. Users with profile photos in database:\n";
$usersWithPhotos = DB::table('users')
    ->whereNotNull('profile_photo')
    ->where('profile_photo', '!=', '')
    ->get(['id', 'username', 'firstName', 'lastName', 'email', 'role', 'branch', 'profile_photo']);

if ($usersWithPhotos->isEmpty()) {
    echo "   No users have profile photos!\n\n";
} else {
    foreach ($usersWithPhotos as $user) {
        echo "   User ID: {$user->id}\n";
        echo "   Name: {$user->firstName} {$user->lastName}\n";
        echo "   Role: {$user->role}\n";
        echo "   Branch: {$user->branch}\n";
        echo "   Photo Path (DB): {$user->profile_photo}\n";
        
        // Build URL
        $url = null;
        if (strpos($user->profile_photo, 'http') === 0) {
            $url = $user->profile_photo;
        } else {
            $url = asset('storage/' . $user->profile_photo);
        }
        echo "   Photo URL: {$url}\n";
        
        // Check file exists
        if (!str_starts_with($user->profile_photo, 'http')) {
            $filePath = storage_path('app/public/' . $user->profile_photo);
            $exists = file_exists($filePath);
            $size = $exists ? filesize($filePath) : 0;
            echo "   File exists: " . ($exists ? 'YES' : 'NO') . "\n";
            if ($exists) {
                echo "   File size: " . number_format($size) . " bytes\n";
            }
        }
        echo "\n";
    }
}

// 3. Test API response format
echo "3. Testing API response format...\n";
$testUser = DB::table('users')
    ->whereNotNull('profile_photo')
    ->where('profile_photo', '!=', '')
    ->first();

if ($testUser) {
    echo "   Testing with user: {$testUser->firstName} {$testUser->lastName}\n";
    
    // Simulate what the API returns
    $profilePhotoUrl = null;
    if ($testUser->profile_photo) {
        if (strpos($testUser->profile_photo, 'http') === 0) {
            $profilePhotoUrl = $testUser->profile_photo;
        } else {
            $profilePhotoUrl = asset('storage/' . $testUser->profile_photo);
        }
    }
    
    $apiResponse = [
        'id' => $testUser->id,
        'username' => $testUser->username,
        'firstName' => $testUser->firstName,
        'lastName' => $testUser->lastName,
        'email' => $testUser->email,
        'role' => $testUser->role,
        'branch' => $testUser->branch,
        'profile_photo' => $profilePhotoUrl,
    ];
    
    echo "   API Response:\n";
    echo json_encode($apiResponse, JSON_PRETTY_PRINT) . "\n\n";
} else {
    echo "   No user with profile photo to test\n\n";
}

// 4. Check storage directory
echo "4. Checking storage directory...\n";
$storageDir = storage_path('app/public/profile_photos');
echo "   Storage path: {$storageDir}\n";
if (!is_dir($storageDir)) {
    echo "   ERROR: Directory does NOT exist!\n\n";
} else {
    echo "   OK: Directory exists\n";
    $files = scandir($storageDir);
    $photoFiles = array_filter($files, function($f) { return !in_array($f, ['.', '..']); });
    echo "   Files in directory: " . count($photoFiles) . "\n";
    foreach ($photoFiles as $file) {
        $fullPath = $storageDir . '/' . $file;
        $size = filesize($fullPath);
        echo "     - {$file} (" . number_format($size) . " bytes)\n";
    }
    echo "\n";
}

// 5. Check public storage symlink
echo "5. Checking public storage symlink...\n";
$publicStorage = public_path('storage');
echo "   Public storage path: {$publicStorage}\n";
if (!file_exists($publicStorage)) {
    echo "   ERROR: Symlink does NOT exist!\n";
    echo "   Run: php artisan storage:link\n\n";
} else {
    echo "   OK: Symlink exists\n";
    if (is_link($publicStorage)) {
        echo "   Target: " . readlink($publicStorage) . "\n";
    }
    echo "\n";
}

// 6. Check APP_URL configuration
echo "6. Checking APP_URL configuration...\n";
echo "   APP_URL: " . config('app.url') . "\n";
echo "   Expected for Android emulator: http://10.0.2.2:8000\n\n";

// 7. Test actual employee API query
echo "7. Testing employee API query (what Android receives)...\n";
$employees = DB::table('users')
    ->whereIn('role', ['technician'])
    ->select('id', 'username', 'firstName', 'lastName', 'email', 'role', 'branch', 'profile_photo')
    ->limit(3)
    ->get();

echo "   Found " . $employees->count() . " technicians\n";
foreach ($employees as $emp) {
    $profilePhotoUrl = null;
    if ($emp->profile_photo) {
        if (strpos($emp->profile_photo, 'http') === 0) {
            $profilePhotoUrl = $emp->profile_photo;
        } else {
            $profilePhotoUrl = asset('storage/' . $emp->profile_photo);
        }
    }
    
    echo "   - {$emp->firstName} {$emp->lastName}: " . ($profilePhotoUrl ?: 'NO PHOTO') . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
