<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\RbacCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades_unidad', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organizational_unit_id')->constrained('unidades_organizacionales')->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documentos')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('usuarios')->restrictOnDelete();
            $table->string('title', 180);
            $table->string('activity_type', 40);
            $table->text('instructions');
            $table->string('expected_result', 500);
            $table->string('priority', 20)->default('normal');
            $table->string('status', 24)->default('assigned');
            $table->timestamp('due_at')->nullable();
            $table->text('result_summary')->nullable();
            $table->foreignId('result_document_id')->nullable()->constrained('documentos')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['organizational_unit_id', 'status', 'due_at'], 'unit_activities_status_due_idx');
            $table->index(['document_id', 'status'], 'unit_activities_document_status_idx');
        });

        Schema::create('responsables_actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('actividades_unidad')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('responsibility', 20)->default('support');
            $table->timestamps();
            $table->unique(['activity_id', 'user_id'], 'activity_assignee_unique');
            $table->index(['user_id', 'responsibility'], 'activity_assignee_user_idx');
        });

        Schema::create('avances_actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('actividades_unidad')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('usuarios')->restrictOnDelete();
            $table->text('detail');
            $table->unsignedTinyInteger('progress_percent')->nullable();
            $table->timestamps();
            $table->index(['activity_id', 'created_at'], 'activity_updates_timeline_idx');
        });

        $this->syncAuthorization();
    }

    public function down(): void
    {
        Schema::dropIfExists('avances_actividades');
        Schema::dropIfExists('responsables_actividades');
        Schema::dropIfExists('actividades_unidad');

        $names = collect(RbacCatalog::permissions())->keys()->filter(fn (string $name) => str_starts_with($name, 'activities.'));
        Permission::query()->whereIn('name', $names)->where('guard_name', RbacCatalog::GUARD)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function syncAuthorization(): void
    {
        foreach (RbacCatalog::permissions() as $name => $attributes) {
            Permission::query()->updateOrCreate(
                ['name' => $name, 'guard_name' => RbacCatalog::GUARD],
                $attributes,
            );
        }

        foreach (RbacCatalog::rolePermissions() as $roleName => $permissionNames) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', RbacCatalog::GUARD)->first();
            if ($role) {
                $role->syncPermissions($permissionNames);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
