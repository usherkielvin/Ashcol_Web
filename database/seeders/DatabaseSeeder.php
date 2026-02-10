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
            'firstName' => 'Admin',
            'lastName' => 'User',
            'email' => 'admin@example.com',
        ]);

        // Create technician user
        User::factory()->technician()->create([
            'firstName' => 'Technician',
            'lastName' => 'User',
            'email' => 'technician@example.com',
        ]);

        // Create employee account with specific credentials
        User::create([
            'username' => 'Employee',
            'firstName' => 'Employee',
            'lastName' => 'User',
            'firstName' => 'Employee',
            'lastName' => 'User',
            'email' => 'employee@ashcol.com',
            'password' => \Illuminate\Support\Facades\Hash::make('EMP1234'),
            'role' => User::ROLE_TECHNICIAN,
            'email_verified_at' => now(),
        ]);

        // Create customer user
        User::factory()->customer()->create([
            'firstName' => 'Customer',
            'lastName' => 'User',
            'email' => 'customer@example.com',
        ]);

        // Create additional test users
        User::factory(5)->customer()->create();
        User::factory(2)->technician()->create();

        // Seed sample tickets
        $this->call([
            TicketSeeder::class,
        ]);
    }
}
