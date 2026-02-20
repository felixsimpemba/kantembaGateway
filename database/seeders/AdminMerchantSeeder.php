<?php

namespace Database\Seeders;

use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminMerchantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if admin already exists
        if (Merchant::where('email', 'admin@kantemba.com')->exists()) {
            $this->command->info('Admin user already exists.');
            return;
        }

        $merchant = Merchant::create([
            'name' => 'Super Admin',
            'email' => 'admin@kantemba.com',
            'password' => Hash::make('password'),
            'business_name' => 'Kantemba HQ',
            'status' => 'active',
            'is_admin' => true,
            'webhook_url' => null,
            'webhook_secret' => Str::random(40),
            'balance' => 0,
            'currency' => 'ZMW',
        ]);

        $this->command->info('Admin user created successfully.');
        $this->command->info('Email: admin@kantemba.com');
        $this->command->info('Password: password');
    }
}
