<?php

namespace App\Http\Requests\Auth;

use App\Enums\AuditAction;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Ingrese su correo electrónico institucional.',
            'email.email' => 'Ingrese un correo electrónico válido.',
            'email.max' => 'El correo electrónico no puede superar los 255 caracteres.',
            'password.required' => 'Ingrese su contraseña.',
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $email = Str::lower(trim($this->string('email')->toString()));
        $this->merge(['email' => $email]);

        $authenticated = Auth::guard('web')->attempt([
            'email' => $email,
            'password' => $this->string('password')->toString(),
            'status' => 'active',
        ]);

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey(), 60);

            $inactiveUser = User::query()
                ->where('email', $email)
                ->where('status', UserStatus::INACTIVE->value)
                ->first();

            if (! $inactiveUser || ! Hash::check($this->string('password')->toString(), $inactiveUser->password)) {
                $inactiveUser = null;
            }

            app(AuditLogService::class)->recordAuthenticationFailure($email, $inactiveUser);

            throw ValidationException::withMessages([
                'email' => 'Las credenciales ingresadas no son válidas o la cuenta no está activa.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        app(AuditLogService::class)->record(
            null,
            AuditAction::AUTH_LOGIN_RATE_LIMITED,
            newValues: [
                'email_fingerprint' => substr(hash('sha256', Str::lower(trim($this->string('email')->toString()))), 0, 20),
                'reason' => 'rate_limited',
            ],
        );

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Demasiados intentos de inicio de sesión. Intente nuevamente en {$seconds} segundos.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip());
    }
}
