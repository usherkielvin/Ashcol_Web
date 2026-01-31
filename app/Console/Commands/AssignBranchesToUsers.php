<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BranchAssignmentService;
use Illuminate\Console\Command;

class AssignBranchesToUsers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'users:assign-branches';

    /**
     * The console command description.
     */
    protected $description = 'Assign branches to users who don\'t have branches assigned';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting branch assignment for users...');
        
        $branchService = new BranchAssignmentService();
        
        // Get users without branches (customers only)
        $users = User::where('role', 'customer')
                    ->where(function ($query) {
                        $query->whereNull('branch')
                              ->orWhere('branch', '');
                    })
                    ->get();
        
        $this->info("Found {$users->count()} users without branch assignments.");
        
        $assigned = 0;
        $failed = 0;
        
        foreach ($users as $user) {
            try {
                $branch = $branchService->assignBranchToUser($user);
                if ($branch) {
                    $this->line("✓ Assigned {$branch->name} to user {$user->id} ({$user->firstName} {$user->lastName})");
                    $assigned++;
                } else {
                    $this->error("✗ Failed to assign branch to user {$user->id}");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("✗ Error assigning branch to user {$user->id}: " . $e->getMessage());
                $failed++;
            }
        }
        
        $this->info("\nBranch assignment completed:");
        $this->info("✓ Successfully assigned: {$assigned}");
        if ($failed > 0) {
            $this->error("✗ Failed assignments: {$failed}");
        }
        
        return 0;
    }
}