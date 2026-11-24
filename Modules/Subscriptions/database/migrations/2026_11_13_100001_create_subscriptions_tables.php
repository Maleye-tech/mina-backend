<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('price'); // FCFA
            $table->string('currency', 3)->default('XOF');
            $table->unsignedInteger('duration_days');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('partner_institutions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type'); // 'crous', 'university', 'ngo', 'other'
            $table->string('code')->unique();
            $table->unsignedInteger('max_beneficiaries')->nullable();
            $table->unsignedInteger('current_beneficiaries')->default(0);
            $table->unsignedInteger('subsidy_rate')->default(100); // 100 = gratuit
            $table->boolean('covers_mina_care')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('access_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('partner_institution_id')->nullable()->constrained('partner_institutions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->unsignedInteger('duration_days')->default(30);
            $table->boolean('is_used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->foreignId('partner_institution_id')->nullable()->constrained('partner_institutions')->nullOnDelete();
            $table->foreignId('access_code_id')->nullable()->constrained('access_codes')->nullOnDelete();
            $table->string('status'); // 'trial', 'active', 'expired', 'cancelled'
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('renewed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('access_codes');
        Schema::dropIfExists('partner_institutions');
        Schema::dropIfExists('subscription_plans');
    }
};
