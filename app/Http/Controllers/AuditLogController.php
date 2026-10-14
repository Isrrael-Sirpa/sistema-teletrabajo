<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\AuditResult;
use App\Enums\PermissionName;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentAccessGrant;
use App\Models\DocumentFile;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    /** @var array<string, class-string> */
    private const RESOURCE_TYPES = [
        'user' => User::class,
        'unit' => OrganizationalUnit::class,
        'document_type' => DocumentType::class,
        'document' => Document::class,
        'document_file' => DocumentFile::class,
        'access_grant' => DocumentAccessGrant::class,
    ];

    public function index(Request $request): View
    {
        Gate::authorize(PermissionName::AUDIT_VIEW->value);

        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'actor' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'resource' => ['nullable', Rule::in(array_keys(self::RESOURCE_TYPES))],
            'result' => ['nullable', Rule::enum(AuditResult::class)],
        ]);

        $query = AuditLog::query()->with('actor:id,name,email');

        if (filled($filters['from'] ?? null)) {
            $query->where('occurred_at', '>=', CarbonImmutable::parse($filters['from'])->startOfDay());
        }

        if (filled($filters['to'] ?? null)) {
            $query->where('occurred_at', '<=', CarbonImmutable::parse($filters['to'])->endOfDay());
        }

        if (filled($filters['actor'] ?? null)) {
            $query->where('actor_id', (int) $filters['actor']);
        }

        if (filled($filters['action'] ?? null)) {
            $query->where('action', $filters['action']);
        }

        if (filled($filters['resource'] ?? null)) {
            $query->where('auditable_type', self::RESOURCE_TYPES[$filters['resource']]);
        }

        if (filled($filters['result'] ?? null)) {
            $result = AuditResult::from($filters['result']);
            $query->whereIn('action', AuditAction::valuesForResult($result));
        }

        $logs = $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate((int) config('audit.per_page'))
            ->withQueryString();

        $actors = User::query()
            ->whereIn('id', AuditLog::query()->whereNotNull('actor_id')->select('actor_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('audit.index', [
            'logs' => $logs,
            'actors' => $actors,
            'actions' => AuditAction::cases(),
            'results' => AuditResult::cases(),
            'resourceTypes' => [
                'user' => 'Usuario',
                'unit' => 'Carrera o unidad',
                'document_type' => 'Tipo documental',
                'document' => 'Documento',
                'document_file' => 'Archivo',
                'access_grant' => 'Acceso explícito',
            ],
            'resourceNames' => array_flip(self::RESOURCE_TYPES),
        ]);
    }
}
