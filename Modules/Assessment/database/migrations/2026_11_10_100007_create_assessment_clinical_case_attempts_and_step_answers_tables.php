<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_clinical_case_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinical_case_id')->constrained('assessment_clinical_cases')->restrictOnDelete();
            $table->string('status', 20)->default('in_progress');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'clinical_case_id'], 'assessment_case_attempts_user_case_index');
            $table->index(['user_id', 'status']);
        });

        Schema::create('assessment_clinical_case_step_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_case_attempt_id')->constrained('assessment_clinical_case_attempts')->cascadeOnDelete();
            $table->foreignId('clinical_case_step_id')->constrained('assessment_clinical_case_steps')->restrictOnDelete();
            $table->text('answer');
            $table->timestamps();
            $table->unique(['clinical_case_attempt_id', 'clinical_case_step_id'], 'assessment_case_step_answers_attempt_step_unique');
            $table->index('clinical_case_step_id', 'assessment_case_step_answers_step_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_clinical_case_step_answers');
        Schema::dropIfExists('assessment_clinical_case_attempts');
    }
};
