<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentTypeService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  array{name: string, correlative_label: string, description?: string|null}  $data
     */
    public function create(User $actor, array $data): DocumentType
    {
        return DB::transaction(function () use ($actor, $data): DocumentType {
            $documentType = DocumentType::query()->create([
                ...$data,
                'code' => $this->uniqueCodeFor($data['name']),
                'is_active' => true,
                'created_by' => $actor->getKey(),
            ]);

            $this->audit->record($actor, AuditAction::DOCUMENT_TYPE_CREATED, $documentType, newValues: [
                'public_id' => $documentType->public_id,
                'name' => $documentType->name,
                'code' => $documentType->code,
                'correlative_label' => $documentType->correlative_label,
                'is_active' => $documentType->is_active,
            ]);

            return $documentType;
        });
    }

    /**
     * @param  array{name: string, correlative_label: string, description?: string|null}  $data
     */
    public function update(User $actor, DocumentType $documentType, array $data): DocumentType
    {
        return DB::transaction(function () use ($actor, $documentType, $data): DocumentType {
            $oldValues = $documentType->only(['name', 'correlative_label', 'description', 'is_active']);
            $documentType->update($data);

            $this->audit->record(
                $actor,
                AuditAction::DOCUMENT_TYPE_UPDATED,
                $documentType,
                $oldValues,
                $documentType->only(['name', 'correlative_label', 'description', 'is_active']),
            );

            return $documentType->refresh();
        });
    }

    public function setActive(User $actor, DocumentType $documentType, bool $active): DocumentType
    {
        return DB::transaction(function () use ($actor, $documentType, $active): DocumentType {
            $oldActive = $documentType->is_active;
            $documentType->update(['is_active' => $active]);

            $this->audit->record(
                $actor,
                $active ? AuditAction::DOCUMENT_TYPE_ACTIVATED : AuditAction::DOCUMENT_TYPE_DEACTIVATED,
                $documentType,
                ['is_active' => $oldActive],
                ['is_active' => $active],
            );

            return $documentType->refresh();
        });
    }

    private function uniqueCodeFor(string $name): string
    {
        $base = Str::of($name)->ascii()->lower()->slug('_')->limit(50, '')->toString();

        if ($base === '') {
            $base = 'tipo_'.substr(hash('sha256', Str::lower($name)), 0, 12);
        }

        if (! DocumentType::query()->whereRaw('LOWER(code) = ?', [Str::lower($base)])->exists()) {
            return $base;
        }

        $suffix = '_'.substr(hash('sha256', Str::lower($name)), 0, 8);

        return Str::limit($base, 50 - strlen($suffix), '').$suffix;
    }
}
