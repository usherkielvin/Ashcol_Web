<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Checking specific user profile photo...\n\n";

// Check Dolera John (technician)
$user = DB::table('users')->where('email', 'dol@gmail.com')->first();

if ($user) {
    echo "User: {$user->firstName} {$user->lastName}\n";
    echo "Email: {$user->email}\n";
    echo "Role: {$user->role}\n";
    echo "Profile Photo (DB): " . ($user->profile_photo ?: 'NULL') . "\n";
    
    if ($user->profile_photo) {
        $path = storage_path('app/public/' . $user->profile_photo);
        echo "File exists: " . (file_exists($path) ? 'YES' : 'NO') . "\n";
        echo "Full URL: " . asset('storage/' . $user->profile_photo) . "\n";
        
        // Test API response
        $profilePhotoUrl = null;
        if (strpos($user->profile_photo, 'http') === 0) {
            $profilePhotoUrl = $user->profile_photo;
        } else {
            $profilePhotoUrl = asset('storage/' . $user->profile_photo);
        }
        echo "\nAPI would return: {$profilePhotoUrl}\n";
    }
} else {
    echo "User not found\n";
}

echo "\n\nChecking all users with profile photos:\n";
$users = DB::table('users')
    ->whereNotNull('profile_photo')
    ->where('profile_photo', '!=', '')
    ->get(['id', 'firstName', 'lastName', 'email', 'role', 'profile_photo']);

echo "Total users with photos: " . $users->count() . "\n\n";

foreach ($users as $u) {
    echo "{$u->id} - {$u->firstName} {$u->lastName} ({$u->role})\n";
    echo "  Photo: {$u->profile_photo}\n";
    $url = asset('storage/' . $u->profile_photo);
    echo "  URL: {$url}\n\n";
}
