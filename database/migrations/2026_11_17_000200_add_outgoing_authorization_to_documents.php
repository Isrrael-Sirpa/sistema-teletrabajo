<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table): void {
            $table->string('authorization_status', 20)->default('not_required')->after('general_status');
            $table->foreignId('authorization_requested_by')->nullable()->after('sender_user_id')
                ->constrained('usuarios')->restrictOnDelete();
            $table->timestampTz('authorization_requested_at')->nullable()->after('authorization_requested_by');
            $table->foreignId('authorized_by')->nullable()->after('authorization_requested_at')
                ->constrained('usuarios')->restrictOnDelete();
            $table->timestampTz('authorized_at')->nullable()->after('authorized_by');
            $table->text('authorization_note')->nullable()->after('authorized_at');
            $table->index(['origin_unit_id', 'authorization_status']);
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table): void {
            $table->dropIndex(['origin_unit_id', 'authorization_status']);
            $table->dropConstrainedForeignId('authorization_requested_by');
            $table->dropConstrainedForeignId('authorized_by');
            $table->dropColumn(['authorization_status', 'authorization_requested_at', 'authorized_at', 'authorization_note']);
        });
    }
};
