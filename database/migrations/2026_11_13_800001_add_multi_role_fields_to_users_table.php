<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'partner_institution_id')) {
                $table->foreignId('partner_institution_id')
                    ->nullable()
                    ->constrained('partner_institutions')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'partner_position')) {
                $table->string('partner_position')->nullable();
            }

            if (! Schema::hasColumn('users', 'ambassador_training_date')) {
                $table->timestamp('ambassador_training_date')->nullable();
            }

            if (! Schema::hasColumn('users', 'is_ambassador_verified')) {
                $table->boolean('is_ambassador_verified')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['partner_institution_id']);
            $table->dropColumn([
                'partner_institution_id',
                'partner_position',
                'ambassador_training_date',
                'is_ambassador_verified',
            ]);
        });
    }
};
