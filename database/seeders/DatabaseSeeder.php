<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seeder Admin
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin123'), // password: admin123
            'role' => 'admin',
        ]);

        // Seeder Karyawan
        User::create([
            'name' => 'Karyawan User',
            'email' => 'karyawan@example.com',
            'password' => Hash::make('karyawan123'), // password: karyawan123
            'role' => 'employee',
        ]);

        // Jika mau generate user dummy
        // User::factory(10)->create();
    }
}
