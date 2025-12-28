<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::firstOrCreate(
            ['phone' => '+989123456789'],
            [
                'name' => 'Admin User',
                'email' => 'admin@negarify.com',
                'password' => Hash::make('admin123'), // Change in production
                'is_verified' => true,
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
                'tokens_balance' => 0,
                'role' => 'admin',
            ]
        );
    }
}

