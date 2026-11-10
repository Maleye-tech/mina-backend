<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_flashcard_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flashcard_id')->constrained('assessment_flashcards')->cascadeOnDelete();
            $table->decimal('easiness_factor', 3, 2)->default(2.50);
            $table->unsignedInteger('interval_days')->default(0);
            $table->unsignedSmallInteger('repetitions')->default(0);
            $table->unsignedSmallInteger('lapses')->default(0);
            $table->unsignedTinyInteger('last_quality')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'flashcard_id']);
            $table->index(['user_id', 'due_at']);
        });

        Schema::create('assessment_flashcard_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flashcard_id')->constrained('assessment_flashcards')->restrictOnDelete();
            $table->unsignedTinyInteger('quality');
            $table->decimal('easiness_factor', 3, 2);
            $table->unsignedInteger('interval_days');
            $table->timestamp('due_at');
            $table->timestamps();
            $table->index(['user_id', 'flashcard_id']);
            $table->index('flashcard_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_flashcard_reviews');
        Schema::dropIfExists('assessment_flashcard_states');
    }
};
