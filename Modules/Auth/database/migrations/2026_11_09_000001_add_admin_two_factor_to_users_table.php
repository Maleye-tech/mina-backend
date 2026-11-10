<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_enabled_at')->nullable();
            $table->unsignedBigInteger('two_factor_last_used_counter')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });

        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', DB::table('users')->where('role', 'admin')->select('id'))
            ->delete();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_enabled_at',
                'two_factor_last_used_counter',
                'app_authentication_recovery_codes',
            ]);
        });
    }
};
