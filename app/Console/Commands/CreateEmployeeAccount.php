<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateEmployeeAccount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employee:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create employee account with username Employee and password EMP1234';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if employee account already exists
        $existingUser = User::where('username', 'Employee')->orWhere('email', 'employee@ashcol.com')->first();
        
        if ($existingUser) {
            $this->info('Employee account already exists!');
            $this->info('Username: Employee');
            $this->info('Email: ' . $existingUser->email);
            $this->info('Role: ' . $existingUser->role);
            return Command::SUCCESS;
        }

        // Create employee account
        $user = User::create([
            'username' => 'Employee',
            'firstName' => 'Employee',
            'lastName' => 'User',
            'name' => 'Employee User',
            'email' => 'employee@ashcol.com',
            'password' => Hash::make('EMP1234'),
            'role' => User::ROLE_STAFF,
            'email_verified_at' => now(),
        ]);

        $this->info('Employee account created successfully!');
        $this->info('Username: Employee');
        $this->info('Password: EMP1234');
        $this->info('Email: employee@ashcol.com');
        $this->info('Role: Staff (Employee)');

        return Command::SUCCESS;
    }
}
