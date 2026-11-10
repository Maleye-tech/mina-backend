<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_clinical_cases', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('presentation');
            $table->foreignId('subject_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('level_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('difficulty', 20)->default('medium');
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->text('learning_goals')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'subject_id']);
            $table->index('level_id');
        });

        Schema::create('assessment_clinical_case_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_case_id')->constrained('assessment_clinical_cases')->cascadeOnDelete();
            $table->text('prompt');
            $table->text('question');
            $table->text('expected_answer');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['clinical_case_id', 'sort_order'], 'assessment_case_steps_case_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_clinical_case_steps');
        Schema::dropIfExists('assessment_clinical_cases');
    }
};
