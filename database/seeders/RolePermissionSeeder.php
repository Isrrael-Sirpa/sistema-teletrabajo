<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\RbacCatalog;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RbacCatalog::rolePermissions() as $roleName => $permissions) {
            Role::findByName($roleName, RbacCatalog::GUARD)
                ->syncPermissions($permissions);
        }
    }
}
