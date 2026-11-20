<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('login_code_hash')->nullable();
            $table->timestamp('login_code_expires_at')->nullable();
            $table->unsignedSmallInteger('login_code_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['login_code_hash', 'login_code_expires_at', 'login_code_attempts']);
        });
    }
};
