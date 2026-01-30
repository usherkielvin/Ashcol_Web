<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class BranchAssignmentService
{
    /**
     * Assign branch to user based on their location
     */
    public function assignBranchToUser(User $user)
    {
        if (!$user->location) {
            Log::warning("User {$user->id} has no location set, cannot assign branch");
            return null;
        }

        // Try to find branch by coordinates if user has them
        if ($user->latitude && $user->longitude) {
            $branch = Branch::findNearestBranch($user->latitude, $user->longitude);
            if ($branch) {
                $user->update(['branch' => $branch->name]);
                Log::info("Assigned branch {$branch->name} to user {$user->id} based on coordinates");
                return $branch;
            }
        }

        // Fallback: Find branch by location name
        $branch = Branch::findByLocation($user->location);
        if ($branch) {
            $user->update(['branch' => $branch->name]);
            Log::info("Assigned branch {$branch->name} to user {$user->id} based on location name");
            return $branch;
        }

        // If no specific branch found, assign default branch
        $defaultBranch = Branch::active()->first();
        if ($defaultBranch) {
            $user->update(['branch' => $defaultBranch->name]);
            Log::info("Assigned default branch {$defaultBranch->name} to user {$user->id}");
            return $defaultBranch;
        }

        Log::warning("No branches available to assign to user {$user->id}");
        return null;
    }

    /**
     * Get branch for ticket creation based on user location
     */
    public function getBranchForTicket(User $user)
    {
        // First check if user already has a branch assigned
        if ($user->branch) {
            $branch = Branch::where('name', $user->branch)->active()->first();
            if ($branch) {
                return $branch;
            }
        }

        // If no branch assigned or branch not found, assign one
        return $this->assignBranchToUser($user);
    }

    /**
     * Location to branch mapping for Philippine cities
     */
    private function getLocationToBranchMapping()
    {
        return [
            // Metro Manila
            'Manila' => 'Manila Central Branch',
            'Manila City' => 'Manila Central Branch',
            'Makati' => 'Makati Business District Branch',
            'Makati City' => 'Makati Business District Branch',
            'Taguig' => 'BGC Taguig Branch',
            'Taguig City' => 'BGC Taguig Branch',
            'Pasay' => 'Pasay Branch',
            'Pasay City' => 'Pasay Branch',
            'Quezon City' => 'Quezon City Branch',
            'Pasig' => 'Pasig Branch',
            'Pasig City' => 'Pasig Branch',
            'Mandaluyong' => 'Mandaluyong Branch',
            'Mandaluyong City' => 'Mandaluyong Branch',
            'San Juan' => 'San Juan Branch',
            'San Juan City' => 'San Juan Branch',
            'Marikina' => 'Marikina Branch',
            'Marikina City' => 'Marikina Branch',
            'Caloocan' => 'North Metro Manila Branch',
            'Caloocan City' => 'North Metro Manila Branch',
            'Valenzuela' => 'North Metro Manila Branch',
            'Valenzuela City' => 'North Metro Manila Branch',
            'Las Piñas' => 'South Metro Manila Branch',
            'Las Piñas City' => 'South Metro Manila Branch',
            'Parañaque' => 'South Metro Manila Branch',
            'Parañaque City' => 'South Metro Manila Branch',
            'Muntinlupa' => 'South Metro Manila Branch',
            'Muntinlupa City' => 'South Metro Manila Branch',
            'Navotas' => 'North Metro Manila Branch',
            'Navotas City' => 'North Metro Manila Branch',
            'Malabon' => 'North Metro Manila Branch',
            'Malabon City' => 'North Metro Manila Branch',
            
            // Rizal Province
            'Antipolo' => 'Rizal Province Branch',
            'Antipolo City' => 'Rizal Province Branch',
            'San Mateo' => 'Rizal Province Branch',
            'Cainta' => 'Rizal Province Branch',
            'Taytay' => 'Rizal Province Branch',
            
            // Cavite Province
            'Bacoor' => 'Cavite Branch',
            'Bacoor City' => 'Cavite Branch',
            'Imus' => 'Cavite Branch',
            'Imus City' => 'Cavite Branch',
            'Dasmariñas' => 'Cavite Branch',
            'Dasmariñas City' => 'Cavite Branch',
            
            // Laguna Province
            'Santa Rosa' => 'Laguna Branch',
            'Santa Rosa City' => 'Laguna Branch',
            'Biñan' => 'Laguna Branch',
            'Biñan City' => 'Laguna Branch',
            'San Pedro' => 'Laguna Branch',
            'San Pedro City' => 'Laguna Branch',
            
            // Bulacan Province
            'Malolos' => 'Bulacan Branch',
            'Malolos City' => 'Bulacan Branch',
            'Meycauayan' => 'Bulacan Branch',
            'Meycauayan City' => 'Bulacan Branch',
            'San Jose del Monte' => 'Bulacan Branch',
            'San Jose del Monte City' => 'Bulacan Branch',
        ];
    }
}