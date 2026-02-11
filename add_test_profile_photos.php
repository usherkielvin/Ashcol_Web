<?php
/**
 * Add test profile photos to users who don't have them
 * This copies the existing profile photo to other users for testing
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Adding test profile photos...\n\n";

// Get the existing profile photo
$sourcePhoto = 'profile_photos/profile_18_1770825331.jpg';
$sourceFile = storage_path('app/public/' . $sourcePhoto);

if (!file_exists($sourceFile)) {
    echo "ERROR: Source photo does not exist: {$sourceFile}\n";
    exit(1);
}

echo "Source photo: {$sourcePhoto}\n";
echo "File size: " . number_format(filesize($sourceFile)) . " bytes\n\n";

// Get users without profile photos
$usersWithoutPhotos = DB::table('users')
    ->whereIn('role', ['technician', 'manager'])
    ->where(function($query) {
        $query->whereNull('profile_photo')
              ->orWhere('profile_photo', '=', '');
    })
    ->get(['id', 'username', 'firstName', 'lastName', 'role']);

echo "Found " . $usersWithoutPhotos->count() . " users without profile photos\n\n";

foreach ($usersWithoutPhotos as $user) {
    // Copy the photo with a new name
    $newFilename = 'profile_' . $user->id . '_' . time() . '.jpg';
    $newPath = 'profile_photos/' . $newFilename;
    $newFile = storage_path('app/public/' . $newPath);
    
    if (copy($sourceFile, $newFile)) {
        // Update database
        DB::table('users')
            ->where('id', $user->id)
            ->update(['profile_photo' => $newPath]);
        
        echo "✓ Added photo for: {$user->firstName} {$user->lastName} ({$user->role})\n";
        echo "  Path: {$newPath}\n";
        echo "  URL: " . asset('storage/' . $newPath) . "\n\n";
    } else {
        echo "✗ Failed to copy photo for: {$user->firstName} {$user->lastName}\n\n";
    }
}

echo "\nDone! All users now have profile photos.\n";
