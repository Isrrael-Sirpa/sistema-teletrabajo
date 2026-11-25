<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archivos_actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('actividades_unidad')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('usuarios')->restrictOnDelete();
            $table->string('disk', 64);
            $table->string('path', 500)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
            $table->index(['activity_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos_actividades');
    }
};
