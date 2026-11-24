<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mental_health_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accessor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('resource_type'); // 'user_test_result', 'appointment', 'conversation'
            $table->unsignedBigInteger('resource_id');
            $table->string('action'); // 'view', 'export', 'delete'
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('accessed_at')->useCurrent();
            $table->timestamps();

            $table->index(['student_id', 'resource_type']);
            $table->index(['accessor_id', 'accessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mental_health_audit_logs');
    }
};
