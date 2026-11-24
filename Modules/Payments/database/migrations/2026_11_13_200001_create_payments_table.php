<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('idempotency_key')->nullable()->unique();
            $table->unsignedInteger('amount'); // FCFA
            $table->string('currency', 3)->default('XOF');
            $table->string('provider')->default('paydunya'); // 'paydunya', 'wave', 'orange_money', 'cinetpay', 'mock'
            $table->string('status')->default('pending'); // 'pending', 'completed', 'failed', 'refunded'
            $table->string('payment_method')->nullable(); // 'wave', 'orange_money', 'card'
            $table->string('provider_reference')->nullable();
            $table->text('checkout_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
