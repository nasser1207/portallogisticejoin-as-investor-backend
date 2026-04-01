<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PortallogisticeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * مدير تجريبي + مستخدم تجريبي للتجربة مع الفرونتند
     */
    public function run(): void
    {
        if (User::where('email', 'admin@tasheel.test')->exists()) {
            return;
        }

        User::create([
            'first_name' => 'مدير',
            'last_name' => 'النظام',
            'name' => 'مدير النظام',
            'email' => 'admin@tasheel.test',
            'phone' => null,
            'national_id' => null,
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'is_verified' => true,
            'is_first_login' => false,
        ]);

        User::create([
            'first_name' => 'مستخدم',
            'last_name' => 'تجريبي',
            'name' => 'مستخدم تجريبي',
            'email' => 'user@tasheel.test',
            'phone' => '0500000000',
            'national_id' => '1234567890',
            'password' => Hash::make('password'),
            'role' => User::ROLE_USER,
            'status' => User::STATUS_ACTIVE,
            'is_verified' => false,
            'is_first_login' => true,
        ]);
    }
}
