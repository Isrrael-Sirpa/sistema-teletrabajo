<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\InstitutionalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoUatUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $initialPassword;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AuthorizationSeeder::class, InstitutionalStructureSeeder::class]);
        $this->initialPassword = bin2hex(random_bytes(12)).'Aa1!';
        config(['demo.user_initial_password' => $this->initialPassword]);
    }

    public function test_command_creates_eighteen_scoped_demo_users_without_changing_administrator(): void
    {
        $admin = User::factory()->create([
            'email' => 'isirpad@fcpn.edu.bo',
            'organizational_unit_id' => null,
            'status' => UserStatus::ACTIVE,
        ]);
        $admin->assignRole(RoleName::ADMINISTRATOR->value);
        $hash = $admin->password;

        $this->assertSame(0, Artisan::call('app:create-demo-uat-users'));
        $this->assertSame(19, User::query()->count());

        foreach (config('institutional_careers') as $careerData) {
            $career = OrganizationalUnit::query()->where('name', $careerData['name'])->sole();
            $heads = User::query()->where('organizational_unit_id', $career->id)
                ->where('status', UserStatus::ACTIVE->value)->role(RoleName::CAREER_HEAD->value)->get();
            $secretaries = User::query()->where('organizational_unit_id', $career->id)
                ->where('status', UserStatus::ACTIVE->value)->role(RoleName::SECRETARY->value)->get();

            $this->assertCount(1, $heads);
            $this->assertCount(1, $secretaries);
            $this->assertStringEndsWith('@example.test', $heads->sole()->email);
            $this->assertStringEndsWith('@example.test', $secretaries->sole()->email);
            $this->assertTrue(Hash::check($this->initialPassword, $heads->sole()->password));
        }

        $this->assertSame($hash, $admin->fresh()->password);
        $this->assertTrue($admin->fresh()->hasRole(RoleName::ADMINISTRATOR->value));
    }

    public function test_command_is_idempotent_and_does_not_replace_existing_passwords(): void
    {
        $admin = User::factory()->create(['email' => 'isirpad@fcpn.edu.bo']);
        $admin->assignRole(RoleName::ADMINISTRATOR->value);

        $this->assertSame(0, Artisan::call('app:create-demo-uat-users'));
        $first = User::query()->where('email', 'jefe.aeronautica@example.test')->sole();
        $hash = $first->password;

        config(['demo.user_initial_password' => bin2hex(random_bytes(12)).'Bb2!']);
        $this->assertSame(0, Artisan::call('app:create-demo-uat-users'));

        $this->assertSame(19, User::query()->count());
        $this->assertSame($hash, $first->fresh()->password);
    }

    public function test_command_rejects_execution_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->assertSame(1, Artisan::call('app:create-demo-uat-users'));
        $this->assertSame(0, User::query()->count());
    }
}
