<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class AssignSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $email = config('services.initial_admin.email');
        $admin = is_string($email) ? User::query()->where('email', $email)->first() : null;

        if (! $admin) {
            throw new RuntimeException('Run AdminUserSeeder with valid initial administrator credentials first.');
        }

        $admin->syncRoles(['Super Admin']);

        $this->command?->info(
            'Super Admin amepewa mamlaka yote kikamilifu.'
        );
    }
}
