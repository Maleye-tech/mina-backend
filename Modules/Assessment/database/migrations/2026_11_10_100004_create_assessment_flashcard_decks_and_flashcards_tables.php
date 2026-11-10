<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_flashcard_decks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('subject_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('level_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'subject_id']);
            $table->index('level_id');
        });

        Schema::create('assessment_flashcards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deck_id')->constrained('assessment_flashcard_decks')->cascadeOnDelete();
            $table->text('front');
            $table->text('back');
            $table->text('hint')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['deck_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_flashcards');
        Schema::dropIfExists('assessment_flashcard_decks');
    }
};
