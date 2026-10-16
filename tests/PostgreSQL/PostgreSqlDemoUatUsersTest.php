<?php

namespace Tests\PostgreSQL;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\InstitutionalStructureSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PostgreSqlDemoUatUsersTest extends TestCase
{
    use DatabaseTransactions;

    public function test_postgresql_demo_load_is_scoped_idempotent_and_preserves_administrator(): void
    {
        $this->seed([AuthorizationSeeder::class, InstitutionalStructureSeeder::class]);
        config(['demo.user_initial_password' => bin2hex(random_bytes(12)).'Aa1!']);

        $admin = User::query()->where('email', 'isirpad@fcpn.edu.bo')->first();
        if (! $admin) {
            $admin = User::factory()->create([
                'email' => 'isirpad@fcpn.edu.bo',
                'organizational_unit_id' => null,
                'status' => UserStatus::ACTIVE,
            ]);
            $admin->assignRole(RoleName::ADMINISTRATOR->value);
        }
        $passwordHash = $admin->password;

        $this->assertSame(0, Artisan::call('app:create-demo-uat-users'));
        $this->assertSame(0, Artisan::call('app:create-demo-uat-users'));
        $this->assertSame(18, User::query()->where('email', 'like', '%@example.test')->count());

        foreach (config('institutional_careers') as $careerData) {
            $career = OrganizationalUnit::query()->where('name', $careerData['name'])->sole();
            $this->assertSame(1, User::query()->where('organizational_unit_id', $career->id)
                ->where('status', UserStatus::ACTIVE->value)->role(RoleName::CAREER_HEAD->value)->count());
            $this->assertSame(1, User::query()->where('organizational_unit_id', $career->id)
                ->where('status', UserStatus::ACTIVE->value)->role(RoleName::SECRETARY->value)->count());
        }

        $this->assertSame($passwordHash, $admin->fresh()->password);
        $this->assertTrue($admin->fresh()->hasRole(RoleName::ADMINISTRATOR->value));
    }
}
