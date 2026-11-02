<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = config('services.initial_admin.name');
        $email = config('services.initial_admin.email');
        $password = config('services.initial_admin.password');

        if (
            ! is_string($name)
            || trim($name) === ''
            || ! is_string($email)
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || ! is_string($password)
            || mb_strlen($password) < 12
        ) {
            throw new RuntimeException(
                'Set INITIAL_ADMIN_NAME, INITIAL_ADMIN_EMAIL, and an INITIAL_ADMIN_PASSWORD of at least 12 characters before seeding.',
            );
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => trim($name),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );
    }
}
