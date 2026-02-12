<?php
/**
 * Test script to verify employee API returns profile photos
 * Run this from command line: php test_employee_api.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Testing Employee API Response\n";
echo "==============================\n\n";

// Get a sample of employees with profile photos
$employees = DB::table('users')
    ->whereIn('role', ['technician', 'manager'])
    ->select('id', 'username', 'firstName', 'lastName', 'email', 'role', 'branch', 'profile_photo')
    ->limit(5)
    ->get();

echo "Found " . $employees->count() . " employees\n\n";

foreach ($employees as $employee) {
    echo "Employee: {$employee->firstName} {$employee->lastName}\n";
    echo "  Email: {$employee->email}\n";
    echo "  Role: {$employee->role}\n";
    echo "  Branch: {$employee->branch}\n";
    echo "  Profile Photo (DB): {$employee->profile_photo}\n";
    
    // Build profile photo URL like the API does
    $profilePhotoUrl = null;
    if ($employee->profile_photo) {
        if (strpos($employee->profile_photo, 'http') === 0) {
            $profilePhotoUrl = $employee->profile_photo;
        } else {
            $profilePhotoUrl = asset('storage/' . $employee->profile_photo);
        }
    }
    echo "  Profile Photo URL: {$profilePhotoUrl}\n";
    
    // Check if file exists
    if ($employee->profile_photo && !str_starts_with($employee->profile_photo, 'http')) {
        $filePath = storage_path('app/public/' . $employee->profile_photo);
        $exists = file_exists($filePath) ? 'YES' : 'NO';
        echo "  File exists: {$exists} ({$filePath})\n";
    }
    
    echo "\n";
}

echo "\nAPP_URL from .env: " . config('app.url') . "\n";
echo "Storage path: " . storage_path('app/public') . "\n";
