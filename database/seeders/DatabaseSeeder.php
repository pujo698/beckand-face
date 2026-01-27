<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔄 Membuat Super Admin...');

        // Super Admin
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('password123'),
                'role' => 'super_admin',
                'position' => 'Super Admin',
                'status' => 'active'
            ]
        );

        $this->command->info("✔ Super Admin berhasil dibuat.");
    }
}
