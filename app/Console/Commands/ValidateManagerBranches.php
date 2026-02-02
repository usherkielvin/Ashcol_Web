<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Branch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ValidateManagerBranches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'managers:validate-branches';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate and fix manager branch assignments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Validating manager branch assignments...');
        
        // Get all managers
        $managers = User::where('role', User::ROLE_MANAGER)->get();
        
        if ($managers->count() === 0) {
            $this->info('No managers found in the system.');
            return Command::SUCCESS;
        }
        
        $this->info("Found {$managers->count()} managers:");
        
        // Get all available branches
        $branches = Branch::active()->get();
        $branchNames = $branches->pluck('name')->toArray();
        
        $this->info("Available branches: " . implode(', ', $branchNames));
        $this->newLine();
        
        $managersWithoutBranch = 0;
        $managersWithInvalidBranch = 0;
        $managersFixed = 0;
        
        foreach ($managers as $manager) {
            $this->info("Manager: {$manager->username} ({$manager->firstName} {$manager->lastName})");
            $this->info("  Email: {$manager->email}");
            $this->info("  Current branch: " . ($manager->branch ?: 'NULL'));
            
            if (!$manager->branch) {
                $managersWithoutBranch++;
                $this->warn("  ❌ No branch assigned");
                
                // Offer to assign a branch
                if ($this->confirm("Assign a branch to this manager?")) {
                    $selectedBranch = $this->choice('Select a branch:', $branchNames);
                    $manager->branch = $selectedBranch;
                    $manager->save();
                    $this->info("  ✅ Assigned branch: {$selectedBranch}");
                    $managersFixed++;
                }
            } else {
                // Check if branch exists in branches table
                $branchExists = in_array($manager->branch, $branchNames);
                
                if ($branchExists) {
                    $this->info("  ✅ Branch assignment is valid");
                } else {
                    $managersWithInvalidBranch++;
                    $this->warn("  ❌ Branch '{$manager->branch}' does not exist in branches table");
                    
                    if ($this->confirm("Update to a valid branch?")) {
                        $selectedBranch = $this->choice('Select a branch:', $branchNames);
                        $manager->branch = $selectedBranch;
                        $manager->save();
                        $this->info("  ✅ Updated branch to: {$selectedBranch}");
                        $managersFixed++;
                    }
                }
            }
            
            $this->newLine();
        }
        
        // Summary
        $this->info('=== SUMMARY ===');
        $this->info("Total managers: {$managers->count()}");
        $this->info("Managers without branch: {$managersWithoutBranch}");
        $this->info("Managers with invalid branch: {$managersWithInvalidBranch}");
        $this->info("Managers fixed: {$managersFixed}");
        
        if ($managersWithoutBranch === 0 && $managersWithInvalidBranch === 0) {
            $this->info('✅ All managers have valid branch assignments!');
        }
        
        return Command::SUCCESS;
    }
}