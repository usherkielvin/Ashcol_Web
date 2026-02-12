<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$users = DB::table('users')
    ->whereNotNull('profile_photo')
    ->where('profile_photo', '!=', '')
    ->get(['id', 'firstName', 'lastName', 'email', 'role', 'profile_photo']);

echo "Users with profile photos:\n";
foreach($users as $u) {
    echo $u->id . ' - ' . $u->firstName . ' ' . $u->lastName . ' (' . $u->role . ') - ' . $u->profile_photo . "\n";
}
