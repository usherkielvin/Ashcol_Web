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
                'name' => 'ASHCOL TAGUIG',
                'location' => 'Taguig City',
                'address' => 'Bonifacio Global City, Taguig, Metro Manila',
                'latitude' => 14.5176,
                'longitude' => 121.0509,
                'is_active' => true,
            ],
            [
                'name' => 'ASHCOL VALENZUELA',
                'location' => 'Valenzuela City',
                'address' => 'Valenzuela City, Metro Manila',
                'latitude' => 14.7000,
                'longitude' => 120.9720,
                'is_active' => true,
            ],
            [
                'name' => 'ASHCOL RODRIGUEZ RIZAL',
                'location' => 'Rodriguez, Rizal',
                'address' => 'Rodriguez, Rizal',
                'latitude' => 14.7297,
                'longitude' => 121.2070,
                'is_active' => true,
            ],
            [
                'name' => 'ASHCOL PAMPANGA',
                'location' => 'San Fernando, Pampanga',
                'address' => 'San Fernando, Pampanga',
                'latitude' => 15.0359,
                'longitude' => 120.6890,
                'is_active' => true,
            ],
            [
                'name' => 'ASHCOL BULACAN',
                'location' => 'Malolos City, Bulacan',
                'address' => 'Malolos City, Bulacan',
                'latitude' => 14.8434,
                'longitude' => 120.8157,
                'is_active' => true,
            ],
            [
                'name' => 'ASHCOL GENTRI CAVITE',
                'location' => 'General Trias, Cavite',
                'address' => 'General Trias, Cavite',
                'latitude' => 14.3866,
                'longitude' => 120.8826,
                'is_active' => true,
            ],
            [
                'name' => 'ASHCOL DASMARINAS CAVITE',
                'location' => 'Dasmariñas, Cavite',
                'address' => 'Dasmariñas, Cavite',
                'latitude' => 14.3294,
                'longitude' => 120.9367,
                'is_active' => true,
            ],
            [
                'name' => 'ASHCOL STA ROSA – TAGAYTAY RD',
                'location' => 'Santa Rosa, Laguna',
                'address' => 'Santa Rosa - Tagaytay Road, Santa Rosa, Laguna',
                'latitude' => 14.3123,
                'longitude' => 121.1114,
                'is_active' => true,
            ],
            [
                'name' => 'ASHCOL LAGUNA',
                'location' => 'Santa Cruz, Laguna',
                'address' => 'Santa Cruz, Laguna',
                'latitude' => 14.2792,
                'longitude' => 121.4166,
                'is_active' => true,
            ],
            [
                'name' => 'ASHCOL BATANGAS',
                'location' => 'Batangas City',
                'address' => 'Batangas City, Batangas',
                'latitude' => 13.7565,
                'longitude' => 121.0583,
                'is_active' => true,
            ],
            [
                'name' => 'ASHCOL CANDELARIA QUEZON PROVINCE',
                'location' => 'Candelaria, Quezon',
                'address' => 'Candelaria, Quezon Province',
                'latitude' => 13.9323,
                'longitude' => 121.4234,
                'is_active' => true,
            ],
        ];

        foreach ($branches as $branch) {
            Branch::updateOrCreate(
                ['name' => $branch['name']], // Check if branch with this name exists
                $branch // If not, create with these values; if yes, update
            );
        }
    }
}