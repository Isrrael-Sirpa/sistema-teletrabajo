<?php

namespace Tests\PostgreSQL;

use App\Enums\OrganizationalUnitType;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgreSqlFrontendThemeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_existing_careers_and_uat_accounts_render_their_own_theme_without_writes(): void
    {
        $this->assertSame('pgsql', DB::getDriverName());
        $this->assertSame(9, OrganizationalUnit::query()->where('type', OrganizationalUnitType::CAREER->value)->count());

        foreach (config('institutional_careers') as $careerData) {
            $career = OrganizationalUnit::query()->where('name', $careerData['name'])->sole();
            $this->assertSame($careerData['acronym'], $career->acronym);

            foreach ([RoleName::CAREER_HEAD, RoleName::SECRETARY] as $role) {
                $user = User::query()->where('organizational_unit_id', $career->id)
                    ->where('status', UserStatus::ACTIVE->value)
                    ->role($role->value)->sole();

                $this->actingAs($user)->get(route('dashboard'))
                    ->assertOk()
                    ->assertSee('data-career="'.$careerData['acronym'].'"', false)
                    ->assertSee('Carrera de '.$career->name)
                    ->assertSee($role->label());
            }
        }

        $administrator = User::query()->where('email', 'isirpad@fcpn.edu.bo')->sole();
        $this->assertSame(UserStatus::ACTIVE, $administrator->status);
        $this->assertTrue($administrator->hasRole(RoleName::ADMINISTRATOR->value));
        $this->actingAs($administrator)->get(route('dashboard'))
            ->assertOk()->assertSee('data-career=""', false);
    }
}
