<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('consent_type', 40);
            $table->boolean('granted');
            $table->string('version', 50);
            $table->timestamps();
            $table->index(['user_id', 'consent_type', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};
