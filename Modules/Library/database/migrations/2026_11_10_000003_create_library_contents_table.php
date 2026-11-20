<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('library_subjects')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('library_levels')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('title');
            $table->string('description')->nullable();
            $table->longText('body')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['subject_id', 'level_id']);
            $table->index(['type', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_contents');
    }
};
