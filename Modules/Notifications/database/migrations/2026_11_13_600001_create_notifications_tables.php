<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'email_notifications')) {
                $table->boolean('email_notifications')->default(true)->after('notifications_enabled');
            }
            if (! Schema::hasColumn('users', 'push_notifications')) {
                $table->boolean('push_notifications')->default(true)->after('email_notifications');
            }
            if (! Schema::hasColumn('users', 'fcm_token')) {
                $table->string('fcm_token')->nullable()->after('push_notifications');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['email_notifications', 'push_notifications', 'fcm_token']);
        });
    }
};
