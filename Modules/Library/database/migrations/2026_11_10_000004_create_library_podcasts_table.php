<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_podcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('library_subjects')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('library_levels')->cascadeOnDelete();
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('original_path');
            $table->string('compressed_path')->nullable();
            $table->string('compression_status', 20)->default('pending');
            $table->string('compression_error')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['subject_id', 'level_id']);
            $table->index(['is_published', 'compression_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_podcasts');
    }
};
