<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class FixEmployeeRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employee:fix-roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix employee roles and check for any role-related issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking technician roles...');
        
        // Check all users and their roles
        $users = User::all();
        
        $this->info('All users in database:');
        $this->table(
            ['ID', 'Username', 'Email', 'Role', 'isTechnician()', 'isAdmin()', 'isManager()'],
            $users->map(function ($user) {
                return [
                    $user->id,
                    $user->username ?? 'N/A',
                    $user->email ?? 'N/A',
                    $user->role ?? 'N/A',
                    $user->isTechnician() ? 'YES' : 'NO',
                    $user->isAdmin() ? 'YES' : 'NO',
                    $user->isManager() ? 'YES' : 'NO',
                ];
            })->toArray()
        );
        
        // Check for users that should be technicians but aren't
        $potentialEmployees = User::where('username', 'like', '%employee%')
            ->orWhere('email', 'like', '%employee%')
            ->orWhere('firstName', 'like', '%employee%')
            ->get();
            
        if ($potentialEmployees->count() > 0) {
            $this->info('Found potential employee accounts:');
            foreach ($potentialEmployees as $user) {
                $this->info("User ID {$user->id}: {$user->username} ({$user->email}) - Role: {$user->role}");
                
                if ($user->role !== User::ROLE_TECHNICIAN) {
                    if ($this->confirm("Update {$user->username} role to 'technician'?")) {
                        $user->role = User::ROLE_TECHNICIAN;
                        $user->save();
                        $this->info("Updated {$user->username} role to 'technician'");
                    }
                }
            }
        }
        
        // Show role constants
        $this->info('Role constants:');
        $this->info('ROLE_ADMIN: ' . User::ROLE_ADMIN);
        $this->info('ROLE_MANAGER: ' . User::ROLE_MANAGER);
        $this->info('ROLE_TECHNICIAN: ' . User::ROLE_TECHNICIAN);
        $this->info('ROLE_CUSTOMER: ' . User::ROLE_CUSTOMER);
        
        return Command::SUCCESS;
    }
}