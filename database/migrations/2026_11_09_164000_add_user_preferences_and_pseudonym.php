<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pseudonym')->nullable()->unique();
            $table->string('preferred_language', 2)->default('fr');
            $table->boolean('notifications_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['pseudonym']);
            $table->dropColumn([
                'pseudonym',
                'preferred_language',
                'notifications_enabled',
            ]);
        });
    }
};
