<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ValidateUserRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:validate-roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate and fix user roles to ensure they match the expected constants';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Validating user roles...');
        
        $validRoles = [
            User::ROLE_ADMIN,
            User::ROLE_MANAGER,
            User::ROLE_STAFF,
            User::ROLE_CUSTOMER,
        ];
        
        // Find users with invalid roles
        $invalidUsers = User::whereNotIn('role', $validRoles)->get();
        
        if ($invalidUsers->count() === 0) {
            $this->info('All user roles are valid!');
            return Command::SUCCESS;
        }
        
        $this->warn("Found {$invalidUsers->count()} users with invalid roles:");
        
        foreach ($invalidUsers as $user) {
            $this->info("User ID {$user->id}: {$user->username} ({$user->email}) - Invalid role: '{$user->role}'");
            
            // Suggest corrections based on common patterns
            $suggestedRole = $this->suggestRole($user->role);
            
            if ($suggestedRole) {
                if ($this->confirm("Update '{$user->role}' to '{$suggestedRole}' for user {$user->username}?")) {
                    $user->role = $suggestedRole;
                    $user->save();
                    $this->info("Updated {$user->username} role to '{$suggestedRole}'");
                }
            } else {
                $this->warn("No suggestion available for role '{$user->role}'. Please update manually.");
            }
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Suggest a valid role based on the invalid role
     */
    private function suggestRole($invalidRole)
    {
        $suggestions = [
            'employee' => User::ROLE_STAFF,
            'emp' => User::ROLE_STAFF,
            'worker' => User::ROLE_STAFF,
            'user' => User::ROLE_CUSTOMER,
            'client' => User::ROLE_CUSTOMER,
            'mgr' => User::ROLE_MANAGER,
            'supervisor' => User::ROLE_MANAGER,
            'administrator' => User::ROLE_ADMIN,
            'root' => User::ROLE_ADMIN,
        ];
        
        return $suggestions[strtolower($invalidRole)] ?? null;
    }
}