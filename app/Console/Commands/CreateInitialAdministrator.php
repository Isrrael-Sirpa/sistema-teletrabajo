<?php

namespace App\Console\Commands;

use App\Services\InitialAdministratorService;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class CreateInitialAdministrator extends Command
{
    protected $signature = 'app:create-initial-admin';

    protected $description = 'Crea de forma interactiva la primera cuenta Administrador institucional';

    public function handle(InitialAdministratorService $service): int
    {
        try {
            if (! $service->bootstrapIsAvailable()) {
                $this->error('El bootstrap fue rechazado porque ya existe una cuenta Administrador.');

                return self::FAILURE;
            }
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $data = [
            'name' => $this->ask('Nombre completo'),
            'email' => $this->ask('Correo electrónico'),
            'password' => $this->secret('Contraseña'),
            'password_confirmation' => $this->secret('Confirme la contraseña'),
        ];

        try {
            $administrator = $service->create($data);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        } catch (DomainException|UniqueConstraintViolationException $exception) {
            $this->error($exception instanceof DomainException
                ? $exception->getMessage()
                : 'No se pudo crear la cuenta porque el correo ya está registrado.');

            return self::FAILURE;
        }

        $this->info("Administrador inicial creado correctamente para {$administrator->email}.");
        $this->line('La contraseña no se mostrará ni quedará registrada por este comando.');

        return self::SUCCESS;
    }
}
