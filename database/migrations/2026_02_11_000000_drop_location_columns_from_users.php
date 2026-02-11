<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, populate region and city for existing users based on their branch
        $this->backfillRegionCityFromBranch();
        
        // Then drop the location-related columns
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['location', 'latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add the columns
        Schema::table('users', function (Blueprint $table) {
            $table->string('location')->nullable()->after('city');
            $table->decimal('latitude', 10, 8)->nullable()->after('location');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });
    }

    /**
     * Backfill region and city for users based on their branch assignment
     */
    private function backfillRegionCityFromBranch(): void
    {
        // Get all users with a branch but missing region or city
        $users = DB::table('users')
            ->whereNotNull('branch')
            ->where(function ($query) {
                $query->whereNull('region')
                      ->orWhereNull('city');
            })
            ->get();

        foreach ($users as $user) {
            $regionCity = $this->extractRegionCityFromBranch($user->branch);
            
            if ($regionCity) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'region' => $regionCity['region'],
                        'city' => $regionCity['city'],
                    ]);
            }
        }
    }

    /**
     * Extract region and city from branch name
     * This is the inverse of Branch::guessFromRegionCity()
     */
    private function extractRegionCityFromBranch(string $branchName): ?array
    {
        $branch = mb_strtolower(trim($branchName), 'UTF-8');

        // NCR branches
        if (str_contains($branch, 'valenzuela')) {
            return ['region' => 'NCR', 'city' => 'Valenzuela'];
        }
        if (str_contains($branch, 'taguig')) {
            return ['region' => 'NCR', 'city' => 'Taguig'];
        }
        if (str_contains($branch, 'rodriguez') || str_contains($branch, 'rizal')) {
            return ['region' => 'NCR', 'city' => 'Rodriguez, Rizal'];
        }

        // Central Luzon branches
        if (str_contains($branch, 'pampanga')) {
            return ['region' => 'Central Luzon', 'city' => 'San Fernando, Pampanga'];
        }
        if (str_contains($branch, 'bulacan')) {
            return ['region' => 'Central Luzon', 'city' => 'Malolos, Bulacan'];
        }

        // CALABARZON branches
        if (str_contains($branch, 'gentri') || str_contains($branch, 'general trias')) {
            return ['region' => 'CALABARZON', 'city' => 'General Trias, Cavite'];
        }
        if (str_contains($branch, 'dasmarinas') || str_contains($branch, 'dasma')) {
            return ['region' => 'CALABARZON', 'city' => 'Dasmariñas, Cavite'];
        }
        if (str_contains($branch, 'sta rosa') || str_contains($branch, 'santa rosa') || str_contains($branch, 'tagaytay')) {
            return ['region' => 'CALABARZON', 'city' => 'Santa Rosa, Laguna'];
        }
        if (str_contains($branch, 'laguna') && !str_contains($branch, 'sta rosa') && !str_contains($branch, 'santa rosa')) {
            return ['region' => 'CALABARZON', 'city' => 'Santa Cruz, Laguna'];
        }
        if (str_contains($branch, 'batangas')) {
            return ['region' => 'CALABARZON', 'city' => 'Batangas City, Batangas'];
        }

        // If no match found, return null
        return null;
    }
};
