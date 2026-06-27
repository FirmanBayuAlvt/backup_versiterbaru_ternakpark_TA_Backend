<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        // Create administrator user (required for login)
        User::firstOrCreate(
            ['email' => 'admin@ternakpark.com'],
            [
                'name' => 'Administrator',
                'email' => 'admin@ternakpark.com',
                'password' => Hash::make('admin123'),
                'role' => 'administrator',
            ]
        );

        // Create general manager user (optional)
        User::firstOrCreate(
            ['email' => 'manager@ternakpark.com'],
            [
                'name' => 'General Manager',
                'email' => 'manager@ternakpark.com',
                'password' => Hash::make('manager123'),
                'role' => 'general_manager',
            ]
        );
    }
}
