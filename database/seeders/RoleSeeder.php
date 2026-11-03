<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Dashboard
            'view dashboard',

            // Members
            'view members',
            'create members',
            'edit members',
            'delete members',
            'restore members',
            'import members',
            'export members',

            // Departments and groups
            'view departments',
            'create departments',
            'edit departments',
            'delete departments',
            'manage departments',
            'view groups',
            'create groups',
            'edit groups',
            'delete groups',
            'manage groups',
            'assign group members',

            // Jumuiya communities
            'view communities',
            'create communities',
            'edit communities',
            'delete communities',
            'assign members to communities',

            // Finance
            'view finances',
            'view Sunday offerings',
            'create Sunday offerings',
            'manage offerings',
            'manage tithes',
            'manage contributions',
            'manage other income',
            'manage expenses',
            'manage finance categories',
            'view offering categories',
            'create offering categories',
            'edit offering categories',
            'toggle offering categories',
            'record envelope offerings',
            'import envelope offerings',
            'approve financial transactions',
            'void financial transactions',
            'reverse financial transactions',
            'view financial reports',
            'export financial reports',
            'view financial receipts',
            'view receipts',
            'manage receipts',
            'manage financial settings',
            'manage SMS settings',
            'send test SMS',
            'view SMS campaigns',
            'send bulk SMS',
            'cancel SMS campaigns',
            'view SMS history',
            'retry failed SMS',

            // Events
            'view events',
            'manage events',

            // Users
            'view users',
            'create users',
            'edit users',
            'delete users',
            'manage roles',
            'assign roles',
            'manage permissions',
            'deactivate users',

            // Settings
            'manage settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $roles = [
            'Super Admin',
            'Mchungaji',
            'Katibu',
            'Mweka Hazina',
            'Mhasibu',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        // Super Admin anapewa ruhusa zote
        $superAdmin = Role::findByName('Super Admin', 'web');
        $superAdmin->syncPermissions(Permission::all());

        // Mchungaji
        Role::findByName('Mchungaji', 'web')->givePermissionTo([
            'view dashboard',
            'view members',
            'export members',
            'view departments',
            'view groups',
            'view communities',
            'view finances',
            'view Sunday offerings',
            'view financial reports',
            'view events',
        ]);

        // Katibu
        Role::findByName('Katibu', 'web')->givePermissionTo([
            'view dashboard',
            'view members',
            'create members',
            'import members',
            'export members',
            'edit members',
            'view departments',
            'manage departments',
            'create departments',
            'edit departments',
            'view groups',
            'create groups',
            'edit groups',
            'assign group members',
            'view communities',
            'create communities',
            'edit communities',
            'assign members to communities',
            'view events',
            'manage events',
            'view SMS campaigns',
            'send bulk SMS',
        ]);

        // Mweka Hazina
        Role::findByName('Mweka Hazina', 'web')->givePermissionTo([
            'view dashboard',
            'view finances',
            'view Sunday offerings',
            'create Sunday offerings',
            'manage offerings',
            'manage tithes',
            'manage contributions',
            'manage other income',
            'manage expenses',
            'manage finance categories',
            'view offering categories',
            'create offering categories',
            'edit offering categories',
            'toggle offering categories',
            'record envelope offerings',
            'import envelope offerings',
            'approve financial transactions',
            'void financial transactions',
            'reverse financial transactions',
            'view financial reports',
            'view financial receipts',
            'view receipts',
            'manage receipts',
            'export financial reports',
            'manage financial settings',
            'manage SMS settings',
            'send test SMS',
            'view SMS history',
            'retry failed SMS',
        ]);

        // Mhasibu
        Role::findByName('Mhasibu', 'web')->givePermissionTo([
            'view dashboard',
            'view finances',
            'view Sunday offerings',
            'manage expenses',
            'record envelope offerings',
            'view financial reports',
            'view financial receipts',
            'view receipts',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
