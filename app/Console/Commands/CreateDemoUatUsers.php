<?php

namespace App\Console\Commands;

use App\Enums\OrganizationalUnitType;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Support\InstitutionalPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CreateDemoUatUsers extends Command
{
    protected $signature = 'app:create-demo-uat-users {--generate-password : Guarda una contraseña aleatoria solo en el .env local}';

    protected $description = 'Crea cuentas demo/UAT para las nueve carreras (solo local/testing)';

    /** @var array<string, array{head: string, secretary: string}> */
    private const EMAILS = [
        'aeronautica' => ['head' => 'jefe.aeronautica@example.test', 'secretary' => 'secretaria.aeronautica@example.test'],
        'construcciones-civiles' => ['head' => 'jefe.construcciones@example.test', 'secretary' => 'secretaria.construcciones@example.test'],
        'electricidad-industrial' => ['head' => 'jefe.electricidad@example.test', 'secretary' => 'secretaria.electricidad@example.test'],
        'electronica-telecomunicaciones' => ['head' => 'jefe.electronica@example.test', 'secretary' => 'secretaria.electronica@example.test'],
        'electromecanica' => ['head' => 'jefe.electromecanica@example.test', 'secretary' => 'secretaria.electromecanica@example.test'],
        'mecanica-automotriz' => ['head' => 'jefe.automotriz@example.test', 'secretary' => 'secretaria.automotriz@example.test'],
        'mecanica-industrial' => ['head' => 'jefe.mecanica.industrial@example.test', 'secretary' => 'secretaria.mecanica.industrial@example.test'],
        'quimica-industrial' => ['head' => 'jefe.quimica@example.test', 'secretary' => 'secretaria.quimica@example.test'],
        'geodesia-topografia-geomatica' => ['head' => 'jefe.geodesia@example.test', 'secretary' => 'secretaria.geodesia@example.test'],
    ];

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Este comando solo puede ejecutarse con APP_ENV=local o testing.');

            return self::FAILURE;
        }

        try {
            $catalog = config('institutional_careers');
            if (! is_array($catalog) || array_keys($catalog) !== array_keys(self::EMAILS)) {
                throw new RuntimeException('El catálogo de carreras no coincide con las nueve cuentas UAT previstas.');
            }

            $password = $this->initialPassword();
            Validator::make(['password' => $password], [
                'password' => ['required', InstitutionalPassword::rule()],
            ])->validate();

            [$created, $existing] = DB::transaction(function () use ($catalog, $password): array {
                $admin = User::query()->whereRaw('LOWER(email) = ?', ['isirpad@fcpn.edu.bo'])->sole();
                if ($admin->status !== UserStatus::ACTIVE || ! $admin->hasRole(RoleName::ADMINISTRATOR->value)) {
                    throw new RuntimeException('El Administrador protegido no está activo con su rol institucional.');
                }

                $faculty = OrganizationalUnit::query()
                    ->where('type', OrganizationalUnitType::FACULTY->value)->sole();
                $careers = OrganizationalUnit::query()->careers()->orderBy('id')->lockForUpdate()->get();
                if ($careers->count() !== 9) {
                    throw new RuntimeException('Deben existir exactamente las nueve carreras antes de crear usuarios UAT.');
                }

                foreach ([RoleName::CAREER_HEAD, RoleName::SECRETARY] as $role) {
                    if (! Role::query()->where('name', $role->value)->where('guard_name', 'web')->exists()) {
                        throw new RuntimeException("Falta el rol institucional {$role->value}.");
                    }
                }

                $rawAdmin = DB::table((new User)->getTable())->where('id', $admin->id)->sole();
                $adminFingerprint = hash('sha256', json_encode($rawAdmin, JSON_THROW_ON_ERROR));
                $created = 0;
                $existing = 0;

                foreach ($catalog as $key => $item) {
                    $career = $careers->firstWhere('name', $item['name']);
                    if (! $career || $career->parent_id !== $faculty->id || ! $career->is_active) {
                        throw new RuntimeException("La carrera {$item['name']} no existe, no está activa o tiene otro padre.");
                    }

                    foreach ([
                        [RoleName::CAREER_HEAD, self::EMAILS[$key]['head'], 'Jefe Demo — '.$career->name],
                        [RoleName::SECRETARY, self::EMAILS[$key]['secretary'], 'Secretaria Demo — '.$career->name],
                    ] as [$role, $email, $name]) {
                        $current = User::query()->whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();

                        if ($role === RoleName::CAREER_HEAD) {
                            $head = User::query()->where('organizational_unit_id', $career->id)
                                ->where('status', UserStatus::ACTIVE->value)
                                ->role(RoleName::CAREER_HEAD->value)->first();
                            if ($head && (! $current || $head->id !== $current->id)) {
                                throw new RuntimeException("La carrera {$career->name} ya tiene otro Jefe activo.");
                            }
                        }

                        if ($current) {
                            if ($current->organizational_unit_id !== $career->id
                                || $current->status !== UserStatus::ACTIVE
                                || ! $current->hasRole($role->value)
                                || $current->getRoleNames()->count() !== 1) {
                                throw new RuntimeException("La cuenta {$email} existe con un ámbito, estado o rol diferente.");
                            }

                            $existing++;

                            continue;
                        }

                        $user = User::query()->create([
                            'name' => $name,
                            'email' => Str::lower($email),
                            'password' => $password,
                            'status' => UserStatus::ACTIVE,
                            'organizational_unit_id' => $career->id,
                        ]);
                        $user->assignRole($role->value);
                        $created++;
                    }
                }

                $after = DB::table((new User)->getTable())->where('id', $admin->id)->sole();
                if (hash('sha256', json_encode($after, JSON_THROW_ON_ERROR)) !== $adminFingerprint) {
                    throw new RuntimeException('La cuenta del Administrador cambió durante la carga.');
                }

                return [$created, $existing];
            });

            $this->info("Cuentas demo/UAT: {$created} creadas, {$existing} ya existentes; 9 Jefes y 9 Secretarias previstos.");
            $this->line('No se muestra ninguna contraseña ni hash.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('No se creó ninguna carga parcial: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function initialPassword(): string
    {
        $configured = config('demo.user_initial_password');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if (! $this->option('generate-password') || ! app()->environment('local')) {
            throw new RuntimeException('Configure DEMO_USER_INITIAL_PASSWORD en el .env local o use --generate-password.');
        }

        $envPath = base_path('.env');
        if (! is_file($envPath) || ! is_writable($envPath)) {
            throw new RuntimeException('El .env local no existe o no admite escritura.');
        }

        $contents = file_get_contents($envPath);
        if (! is_string($contents) || preg_match('/^DEMO_USER_INITIAL_PASSWORD=/m', $contents)) {
            throw new RuntimeException('La variable DEMO_USER_INITIAL_PASSWORD ya existe en .env; revísela sin sobrescribirla.');
        }

        $password = bin2hex(random_bytes(20)).'Aa1!';
        if (file_put_contents($envPath, PHP_EOL.'DEMO_USER_INITIAL_PASSWORD="'.$password.'"'.PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('No se pudo guardar la contraseña inicial en el .env local.');
        }

        $this->line('Se generó una contraseña inicial y se guardó únicamente en el .env local ignorado por Git.');

        return $password;
    }
}
