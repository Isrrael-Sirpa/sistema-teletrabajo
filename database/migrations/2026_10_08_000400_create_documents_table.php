<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('code', 80)->nullable()->unique();
            $table->foreignId('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->foreignId('origin_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('subject', 255);
            $table->text('description')->nullable();
            $table->string('general_status', 20)->default('draft');
            $table->string('priority', 20)->default('normal');
            $table->string('confidentiality', 20)->default('normal');
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('annulled_at')->nullable();
            $table->foreignId('annulled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('annulment_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestampsTz();

            $table->index(['origin_unit_id', 'general_status', 'created_at']);
            $table->index(['general_status', 'priority']);
            $table->index(['due_at', 'general_status']);
            $table->index('created_by');
            $table->index('sender_user_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE documents ADD CONSTRAINT docs_status_check CHECK (general_status IN ('draft', 'sent', 'received', 'in_review', 'approved', 'returned', 'rejected', 'annulled'))");
            DB::statement("ALTER TABLE documents ADD CONSTRAINT docs_priority_check CHECK (priority IN ('normal', 'important', 'urgent'))");
            DB::statement("ALTER TABLE documents ADD CONSTRAINT docs_confidentiality_check CHECK (confidentiality IN ('normal', 'restricted'))");
            DB::statement("ALTER TABLE documents ADD CONSTRAINT docs_sent_fields_check CHECK ((general_status = 'draft' AND code IS NULL AND sent_at IS NULL) OR (general_status <> 'draft' AND code IS NOT NULL AND sent_at IS NOT NULL))");
            DB::statement("ALTER TABLE documents ADD CONSTRAINT docs_annul_check CHECK ((general_status = 'annulled' AND annulled_at IS NOT NULL AND annulled_by IS NOT NULL AND LENGTH(BTRIM(annulment_reason)) > 0) OR (general_status <> 'annulled' AND annulled_at IS NULL AND annulled_by IS NULL AND annulment_reason IS NULL))");
            DB::statement('ALTER TABLE documents ADD CONSTRAINT docs_lock_version_check CHECK (lock_version >= 0)');
            DB::statement("CREATE INDEX documents_due_open_index ON documents (due_at) WHERE due_at IS NOT NULL AND general_status NOT IN ('approved', 'rejected', 'annulled')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
