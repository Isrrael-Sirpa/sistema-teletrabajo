<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\RbacCatalog;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RbacCatalog::roles() as $name => $attributes) {
            Role::query()->updateOrCreate(
                ['name' => $name, 'guard_name' => RbacCatalog::GUARD],
                $attributes,
            );
        }
    }
}
