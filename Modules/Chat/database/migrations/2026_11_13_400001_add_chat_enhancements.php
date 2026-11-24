<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->boolean('is_with_ambassador')->default(false)->after('last_message_at');
            $table->timestamp('crisis_flagged_at')->nullable()->after('is_with_ambassador');
            $table->string('status')->default('active')->after('crisis_flagged_at');
        });

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->boolean('is_crisis')->default(false)->after('body');
            $table->json('crisis_keywords')->nullable()->after('is_crisis');
            $table->softDeletes()->after('read_at');
        });

        Schema::create('chat_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('chat_messages')->nullOnDelete();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('reason');
            $table->string('status')->default('pending'); // 'pending', 'reviewed', 'dismissed'
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_reports');

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn(['is_crisis', 'crisis_keywords']);
        });

        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropColumn(['is_with_ambassador', 'crisis_flagged_at', 'status']);
        });
    }
};
