<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed ticket statuses first
        $this->call([
            TicketStatusSeeder::class,
            BranchSeeder::class,
        ]);

        // Create admin user
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        // Create staff user
        User::factory()->staff()->create([
            'name' => 'Staff User',
            'email' => 'staff@example.com',
        ]);

        // Create employee account with specific credentials
        User::create([
            'username' => 'Employee',
            'firstName' => 'Employee',
            'lastName' => 'User',
            'name' => 'Employee User',
            'email' => 'employee@ashcol.com',
            'password' => \Illuminate\Support\Facades\Hash::make('EMP1234'),
            'role' => User::ROLE_STAFF,
            'email_verified_at' => now(),
        ]);

        // Create customer user
        User::factory()->customer()->create([
            'name' => 'Customer User',
            'email' => 'customer@example.com',
        ]);

        // Create additional test users
        User::factory(5)->customer()->create();
        User::factory(2)->staff()->create();

        // Seed sample tickets
        $this->call([
            TicketSeeder::class,
        ]);
    }
}
