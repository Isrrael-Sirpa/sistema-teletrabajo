<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->index('created_by');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->index('document_type_id');
            $table->index('annulled_by');
        });

        Schema::table('document_recipients', function (Blueprint $table) {
            $table->index('received_by');
            $table->index('decided_by');
        });

        Schema::table('document_versions', function (Blueprint $table) {
            $table->index('uploaded_by');
        });

        Schema::table('document_files', function (Blueprint $table) {
            $table->index('uploaded_by');
        });

        Schema::table('document_observations', function (Blueprint $table) {
            $table->index('author_id');
        });

        Schema::table('document_access_grants', function (Blueprint $table) {
            $table->index('granted_by');
            $table->index('revoked_by');
        });

        Schema::table('document_events', function (Blueprint $table) {
            $table->index('document_recipient_id');
        });

        Schema::table('temporary_delegations', function (Blueprint $table) {
            $table->index('authorized_by');
            $table->index('revoked_by');
        });
    }

    public function down(): void
    {
        Schema::table('temporary_delegations', function (Blueprint $table) {
            $table->dropIndex(['authorized_by']);
            $table->dropIndex(['revoked_by']);
        });

        Schema::table('document_events', function (Blueprint $table) {
            $table->dropIndex(['document_recipient_id']);
        });

        Schema::table('document_access_grants', function (Blueprint $table) {
            $table->dropIndex(['granted_by']);
            $table->dropIndex(['revoked_by']);
        });

        Schema::table('document_observations', function (Blueprint $table) {
            $table->dropIndex(['author_id']);
        });

        Schema::table('document_files', function (Blueprint $table) {
            $table->dropIndex(['uploaded_by']);
        });

        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropIndex(['uploaded_by']);
        });

        Schema::table('document_recipients', function (Blueprint $table) {
            $table->dropIndex(['received_by']);
            $table->dropIndex(['decided_by']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['document_type_id']);
            $table->dropIndex(['annulled_by']);
        });

        Schema::table('document_types', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
        });
    }
};
