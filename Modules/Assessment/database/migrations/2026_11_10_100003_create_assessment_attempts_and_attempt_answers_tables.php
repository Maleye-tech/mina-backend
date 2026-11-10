<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('level_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('in_progress');
            $table->decimal('score', 5, 2)->nullable();
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('time_spent_seconds')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index('subject_id');
        });

        Schema::create('assessment_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('assessment_questions')->restrictOnDelete();
            $table->jsonb('selected_option_ids')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->unique(['attempt_id', 'question_id']);
            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_attempt_answers');
        Schema::dropIfExists('assessment_attempts');
    }
};
