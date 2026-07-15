// database/seeders/DatabaseSeeder.php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Test user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@pakpaisa.com',
            'phone' => '03001234567',
            'cnic' => '12345-1234567-8',
            'password' => Hash::make('password123'),
            'pin' => Hash::make('1234'),
            'kyc_status' => 'approved',
            'phone_verified_at' => now(),
        ]);

        Wallet::create([
            'user_id' => $user->id,
            'wallet_id' => 'PKW00000001',
            'balance' => 10000,
            'pending_balance' => 0,
            'currency' => 'PKR',
        ]);

        // Admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@pakpaisa.com',
            'phone' => '03001112222',
            'cnic' => '11111-1111111-1',
            'password' => Hash::make('admin123'),
            'pin' => Hash::make('0000'),
            'kyc_status' => 'approved',
            'phone_verified_at' => now(),
            'is_admin' => true,
        ]);

        Wallet::create([
            'user_id' => $admin->id,
            'wallet_id' => 'PKW00000002',
            'balance' => 50000,
            'pending_balance' => 0,
            'currency' => 'PKR',
        ]);
    }
}
