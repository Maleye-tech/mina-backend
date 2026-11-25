<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained('appointment_professionals')->cascadeOnDelete();
            $table->string('topic');
            $table->string('priority')->default('medium'); // low, medium, high, urgent
            $table->string('status')->default('pending'); // pending, accepted, declined, completed
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('scheduled_for')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['professional_id', 'status']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('private_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('chat_conversations')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type'); // crisis_keyword, system, reminder
            $table->string('severity'); // low, medium, high, critical
            $table->string('title');
            $table->text('message');
            $table->string('status')->default('open'); // open, acknowledged, resolved
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('private_notes');
        Schema::dropIfExists('consultation_requests');
    }
};
