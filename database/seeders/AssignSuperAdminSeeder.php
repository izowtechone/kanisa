<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AssignSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = User::where('email', 'admin@kkktcms.local')->first();

        if (! $admin) {
            $this->command->error('Admin account haijapatikana.');

            return;
        }

        $role = Role::findByName('Super Admin', 'web');

        $admin->syncRoles([$role]);

        $this->command->info(
            'Super Admin amepewa mamlaka yote kikamilifu.'
        );
    }
}
