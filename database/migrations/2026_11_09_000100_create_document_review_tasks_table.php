<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Role;
use App\Support\RbacCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareas_revision_documentos', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('document_recipient_id')->constrained('destinatarios_documentos')->restrictOnDelete();
            $table->uuid('assignment_key');
            $table->foreignId('document_version_id')->nullable()->constrained('versiones_documentos')->restrictOnDelete();
            $table->foreignId('assigned_to')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('usuarios')->restrictOnDelete();
            $table->text('instructions');
            $table->string('recommendation', 20)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['document_recipient_id', 'assignment_key'], 'review_tasks_assignment_key_unique');
            $table->index(['assigned_to', 'submitted_at', 'closed_at'], 'review_tasks_assignee_dates_idx');
            $table->index('assigned_by');
            $table->index('document_version_id');
            $table->index(['document_recipient_id', 'submitted_at', 'closed_at'], 'review_tasks_recipient_status_index');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'])) {
            DB::statement(<<<'SQL'
                ALTER TABLE tareas_revision_documentos ADD CONSTRAINT review_task_result_check CHECK (
                    (submitted_at IS NULL AND recommendation IS NULL AND message IS NULL)
                    OR (submitted_at IS NOT NULL AND closed_at IS NULL
                        AND recommendation IS NOT NULL AND recommendation IN ('approve', 'reject')
                        AND message IS NOT NULL AND LENGTH(TRIM(message)) > 0)
                )
                SQL);
        }

        // Install the role in existing environments. A fresh database receives its permissions from the RBAC seeders.
        $name = RoleName::KARDEX_HEAD->value;
        $role = Role::query()->firstOrCreate(
            ['name' => $name, 'guard_name' => RbacCatalog::GUARD],
            RbacCatalog::roles()[$name],
        );
        $role->givePermissionTo($role->getPermissionClass()::query()
            ->where('guard_name', RbacCatalog::GUARD)
            ->whereIn('name', RbacCatalog::rolePermissions()[$name])
            ->get());
        $this->updateSecretaryPermissions(restore: false);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas_revision_documentos');
        Role::query()->where('name', RoleName::KARDEX_HEAD->value)
            ->where('guard_name', RbacCatalog::GUARD)->first()?->delete();
        $this->updateSecretaryPermissions(restore: true);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function updateSecretaryPermissions(bool $restore): void
    {
        $secretary = Role::query()->where('name', RoleName::SECRETARY->value)
            ->where('guard_name', RbacCatalog::GUARD)->first();

        if ($secretary) {
            $permissionIds = $secretary->getPermissionClass()::query()
                ->where('guard_name', RbacCatalog::GUARD)
                ->whereIn('name', [
                    PermissionName::DOCUMENTS_APPROVE->value,
                    PermissionName::DOCUMENTS_RETURN->value,
                    PermissionName::DOCUMENTS_REJECT->value,
                ])->pluck('id');

            if ($restore) {
                $secretary->permissions()->syncWithoutDetaching($permissionIds);
            } else {
                $secretary->permissions()->detach($permissionIds);
            }
        }
    }
};
