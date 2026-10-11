<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            [
                'email' => 'user-6ea995ed@example.test',
            ],
            [
                'name' => 'Admin Catkuji',
                'phone' => '080000000001',
                'password' => \Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(24))),
                'email_verified_at' => now(),
                'role' => 'super_admin',
                'status' => 'active',
            ]
        );

        Wallet::query()->firstOrCreate(
            [
                'user_id' => $admin->id,
            ],
            [
                'balance' => 0,
            ]
        );
    }
}