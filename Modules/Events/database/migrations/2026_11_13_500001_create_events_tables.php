<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('type'); // 'workshop', 'webinar', 'ambassador_training'
            $table->text('description')->nullable();
            $table->string('presenter_name')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('location_or_link')->nullable();
            $table->unsignedInteger('max_participants')->nullable();
            $table->unsignedInteger('current_participants')->default(0);
            $table->string('replay_url')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['type', 'starts_at']);
        });

        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('registered'); // 'registered', 'attended', 'cancelled'
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamp('attended_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('events');
    }
};
