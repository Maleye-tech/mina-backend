<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('level_id')->constrained()->restrictOnDelete();
            $table->text('question');
            $table->string('question_type', 20)->default('single');
            $table->string('difficulty', 20)->default('medium');
            $table->string('status', 20)->default('draft');
            $table->text('explanation')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('fingerprint', 64)->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['subject_id', 'level_id', 'status']);
            $table->index('difficulty');
        });

        Schema::create('assessment_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('assessment_questions')->cascadeOnDelete();
            $table->text('label');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['question_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_question_options');
        Schema::dropIfExists('assessment_questions');
    }
};
