<?php
/**
 * Test the actual API endpoint that Android calls
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$manager = DB::table('users')->where('role', 'manager')->first();
if (!$manager) {
    echo "No manager found in database\n";
    exit(1);
}

echo "Testing employees for manager: {$manager->firstName} {$manager->lastName}\n";
echo "Branch: {$manager->branch}\n\n";

// Get employees for this manager's branch
$employees = DB::table('users')
    ->whereIn('role', ['technician'])
    ->where('branch', $manager->branch)
    ->select('id', 'username', 'firstName', 'lastName', 'email', 'role', 'branch', 'profile_photo')
    ->get();

echo "Found " . $employees->count() . " technicians in branch: {$manager->branch}\n\n";

foreach ($employees as $emp) {
    $profilePhotoUrl = null;
    if ($emp->profile_photo) {
        if (strpos($emp->profile_photo, 'http') === 0) {
            $profilePhotoUrl = $emp->profile_photo;
        } else {
            $profilePhotoUrl = asset('storage/' . $emp->profile_photo);
        }
    }
    
    echo "Employee: {$emp->firstName} {$emp->lastName}\n";
    echo "  Email: {$emp->email}\n";
    echo "  Role: {$emp->role}\n";
    echo "  Branch: {$emp->branch}\n";
    echo "  Profile Photo URL: " . ($profilePhotoUrl ?: 'NULL') . "\n\n";
}

echo "\nSimulated API JSON Response:\n";
$formattedEmployees = [];
foreach ($employees as $emp) {
    $profilePhotoUrl = null;
    if ($emp->profile_photo) {
        if (strpos($emp->profile_photo, 'http') === 0) {
            $profilePhotoUrl = $emp->profile_photo;
        } else {
            $profilePhotoUrl = asset('storage/' . $emp->profile_photo);
        }
    }
    
    $formattedEmployees[] = [
        'id' => $emp->id,
        'username' => $emp->username,
        'firstName' => $emp->firstName,
        'lastName' => $emp->lastName,
        'email' => $emp->email,
        'role' => $emp->role,
        'branch' => $emp->branch,
        'ticket_count' => 0,
        'profile_photo' => $profilePhotoUrl,
    ];
}

$response = [
    'success' => true,
    'employees' => $formattedEmployees,
    'branch' => $manager->branch,
    'employee_count' => count($formattedEmployees),
];

echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
