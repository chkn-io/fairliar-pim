<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Create admin user if it doesn't exist
        if (!User::where('email', 'admin@pim-fairliar.com')->exists()) {
            User::create([
                'name' => 'Administrator',
                'email' => 'admin@pim-fairliar.com',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true,
            ]);

            $this->command->info('Admin user created successfully!');
            $this->command->info('Email: admin@pim-fairliar.com');
            $this->command->info('Password: admin123');
        } else {
            $this->command->info('Admin user already exists.');
        }

        // Create a sample staff user
        if (!User::where('email', 'staff@pim-fairliar.com')->exists()) {
            User::create([
                'name' => 'Staff User',
                'email' => 'staff@pim-fairliar.com',
                'password' => Hash::make('staff123'),
                'role' => 'staff',
                'is_active' => true,
            ]);

            $this->command->info('Staff user created successfully!');
            $this->command->info('Email: staff@pim-fairliar.com');
            $this->command->info('Password: staff123');
        } else {
            $this->command->info('Staff user already exists.');
        }
    }
}
