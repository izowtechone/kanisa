<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@kkktcms.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('ChangeMe@2026!'),
                'email_verified_at' => now(),
            ]
        );
    }
}
