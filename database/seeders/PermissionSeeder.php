<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\RbacCatalog;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RbacCatalog::permissions() as $name => $attributes) {
            Permission::query()->updateOrCreate(
                ['name' => $name, 'guard_name' => RbacCatalog::GUARD],
                $attributes,
            );
        }
    }
}
