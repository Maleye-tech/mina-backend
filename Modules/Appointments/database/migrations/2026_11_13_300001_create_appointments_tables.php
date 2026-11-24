<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_professionals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->default('Psychologue clinicien');
            $table->text('bio')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedInteger('consultation_duration_minutes')->default(45);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('appointment_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('professional_id')->constrained('appointment_professionals')->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_booked')->default(false);
            $table->timestamps();

            $table->index(['professional_id', 'starts_at']);
        });

        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained('appointment_professionals')->cascadeOnDelete();
            $table->foreignId('slot_id')->nullable()->constrained('appointment_slots')->nullOnDelete();
            $table->timestamp('scheduled_at');
            $table->unsignedInteger('duration_minutes')->default(45);
            $table->string('status')->default('pending'); // 'pending', 'confirmed', 'cancelled', 'completed'
            $table->string('consultation_type')->default('video'); // 'video', 'in_person', 'chat'
            $table->string('meeting_link')->nullable();
            $table->text('student_notes')->nullable(); // encrypted
            $table->text('confidential_notes')->nullable(); // encrypted (reserved to professional)
            $table->string('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['professional_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('appointment_slots');
        Schema::dropIfExists('appointment_professionals');
    }
};
