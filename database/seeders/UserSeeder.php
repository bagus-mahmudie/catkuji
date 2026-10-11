<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'user-3965079a@example.test'],
            [
                'name' => 'Admin',
                'phone' => '081111111111',
                'password' => \Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(24))),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'user-92eb56bc@example.test'],
            [
                'name' => 'Sample User',
                'phone' => '082222222222',
                'password' => \Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(24))),
                'role' => 'user',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
    }
}