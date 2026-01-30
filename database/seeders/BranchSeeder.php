<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            [
                'name' => 'Manila Central Branch',
                'location' => 'Manila City',
                'address' => 'Ermita, Manila, Metro Manila',
                'latitude' => 14.5995,
                'longitude' => 120.9842,
                'is_active' => true,
            ],
            [
                'name' => 'Makati Business District Branch',
                'location' => 'Makati City',
                'address' => 'Ayala Avenue, Makati, Metro Manila',
                'latitude' => 14.5547,
                'longitude' => 121.0244,
                'is_active' => true,
            ],
            [
                'name' => 'BGC Taguig Branch',
                'location' => 'Taguig City',
                'address' => 'Bonifacio Global City, Taguig, Metro Manila',
                'latitude' => 14.5176,
                'longitude' => 121.0509,
                'is_active' => true,
            ],
            [
                'name' => 'Quezon City Branch',
                'location' => 'Quezon City',
                'address' => 'Diliman, Quezon City, Metro Manila',
                'latitude' => 14.6507,
                'longitude' => 121.1029,
                'is_active' => true,
            ],
            [
                'name' => 'Pasig Branch',
                'location' => 'Pasig City',
                'address' => 'Ortigas Center, Pasig, Metro Manila',
                'latitude' => 14.5764,
                'longitude' => 121.0851,
                'is_active' => true,
            ],
            [
                'name' => 'North Metro Manila Branch',
                'location' => 'Caloocan City',
                'address' => 'Caloocan City, Metro Manila',
                'latitude' => 14.7587,
                'longitude' => 120.9637,
                'is_active' => true,
            ],
            [
                'name' => 'South Metro Manila Branch',
                'location' => 'Las Piñas City',
                'address' => 'Las Piñas City, Metro Manila',
                'latitude' => 14.4378,
                'longitude' => 120.9947,
                'is_active' => true,
            ],
            [
                'name' => 'Rizal Province Branch',
                'location' => 'Antipolo City',
                'address' => 'Antipolo City, Rizal',
                'latitude' => 14.5932,
                'longitude' => 121.1815,
                'is_active' => true,
            ],
            [
                'name' => 'Cavite Branch',
                'location' => 'Bacoor City',
                'address' => 'Bacoor City, Cavite',
                'latitude' => 14.4590,
                'longitude' => 120.9447,
                'is_active' => true,
            ],
            [
                'name' => 'Laguna Branch',
                'location' => 'Santa Rosa City',
                'address' => 'Santa Rosa City, Laguna',
                'latitude' => 14.3123,
                'longitude' => 121.1114,
                'is_active' => true,
            ],
            [
                'name' => 'Bulacan Branch',
                'location' => 'Malolos City',
                'address' => 'Malolos City, Bulacan',
                'latitude' => 14.8434,
                'longitude' => 120.8157,
                'is_active' => true,
            ],
        ];

        foreach ($branches as $branch) {
            Branch::create($branch);
        }
    }
}