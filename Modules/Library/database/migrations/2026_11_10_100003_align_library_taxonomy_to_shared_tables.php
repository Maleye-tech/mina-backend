<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aligne la Library sur la taxonomie commune de l'équipe (tables `levels` et `subjects`).
 * Les anciennes tables `library_levels` et `library_subjects` sont supprimées.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['library_contents', 'library_podcasts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['subject_id']);
                $table->dropForeign(['level_id']);
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
                $table->foreign('level_id')->references('id')->on('levels')->cascadeOnDelete();
            });
        }

        Schema::dropIfExists('library_levels');
        Schema::dropIfExists('library_subjects');
    }

    public function down(): void
    {
        Schema::create('library_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('library_subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        foreach (['library_contents', 'library_podcasts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['subject_id']);
                $table->dropForeign(['level_id']);
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('subject_id')->references('id')->on('library_subjects')->cascadeOnDelete();
                $table->foreign('level_id')->references('id')->on('library_levels')->cascadeOnDelete();
            });
        }
    }
};
