<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class AuditLogService
{
    private const FORBIDDEN_KEYS = [
        'password', 'password_confirmation', 'remember_token', 'token', 'tokens',
        'secret', 'secrets', 'authorization', 'authorization_header', 'cookie',
        'cookies', 'session_id', 'sessionid',
    ];

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(
        ?User $actor,
        AuditAction $action,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
    ): AuditLog {
        $request = app()->bound('request') ? request() : null;

        return AuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $this->userAgent($request),
            'old_values' => $this->sanitize($oldValues) ?: null,
            'new_values' => $this->sanitize($newValues) ?: null,
            'request_id' => $this->requestId($request),
            'occurred_at' => now(),
        ]);
    }

    public function recordAuthenticationFailure(string $email, ?User $inactiveUser = null): AuditLog
    {
        return $this->record(
            null,
            $inactiveUser ? AuditAction::AUTH_LOGIN_DENIED_INACTIVE : AuditAction::AUTH_LOGIN_FAILED,
            $inactiveUser,
            newValues: [
                'email_fingerprint' => substr(hash('sha256', Str::lower(trim($email))), 0, 20),
                'reason' => $inactiveUser ? 'inactive_account' : 'invalid_credentials',
            ],
        );
    }

    /** @param array<string, mixed> $values */
    public function sanitize(array $values): array
    {
        return $this->sanitizeArray($values, 0);
    }

    /** @param array<string|int, mixed> $values */
    private function sanitizeArray(array $values, int $depth): array
    {
        if ($depth >= 4) {
            return [];
        }

        $sanitized = [];

        foreach (array_slice($values, 0, 50, true) as $key => $value) {
            if (is_string($key) && $this->isForbiddenKey($key)) {
                continue;
            }

            if ($value instanceof UploadedFile || is_resource($value)) {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value, $depth + 1);
            } elseif ($value instanceof BackedEnum) {
                $sanitized[$key] = $value->value;
            } elseif ($value instanceof DateTimeInterface) {
                $sanitized[$key] = $value->format(DATE_ATOM);
            } elseif (is_scalar($value) || $value === null) {
                $sanitized[$key] = is_string($value) ? Str::limit($value, 2_000) : $value;
            }
        }

        return $sanitized;
    }

    private function isForbiddenKey(string $key): bool
    {
        $normalized = Str::lower(str_replace(['-', '.', ' '], '_', $key));

        return in_array($normalized, self::FORBIDDEN_KEYS, true)
            || Str::contains($normalized, ['password', 'authorization', 'cookie', 'session_id'])
            || Str::endsWith($normalized, ['_token', '_secret']);
    }

    private function userAgent(?Request $request): ?string
    {
        $userAgent = $request?->userAgent();

        return filled($userAgent) ? Str::limit((string) $userAgent, 1_000) : null;
    }

    private function requestId(?Request $request): string
    {
        if (! $request) {
            return (string) Str::uuid();
        }

        $requestId = $request->attributes->get('audit_request_id');

        if (! is_string($requestId)) {
            $requestId = (string) Str::uuid();
            $request->attributes->set('audit_request_id', $requestId);
        }

        return $requestId;
    }
}
