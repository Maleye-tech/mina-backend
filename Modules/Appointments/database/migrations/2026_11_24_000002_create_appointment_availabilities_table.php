<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('professional_id')->constrained('appointment_professionals')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday')->comment('0=dimanche ... 6=samedi');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['professional_id', 'weekday', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_availabilities');
    }
};
