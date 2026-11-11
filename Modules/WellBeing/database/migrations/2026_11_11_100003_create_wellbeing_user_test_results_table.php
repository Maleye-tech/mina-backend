<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historique des tests passés par les utilisateurs.
 *
 * Le champ `response_data` contient des données de santé détaillées et est
 * chiffré au niveau applicatif par le cast `encrypted:array` du modèle
 * UserTestResult : la base ne stocke jamais le détail des réponses en clair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_id')->constrained('tests')->restrictOnDelete();
            $table->unsignedInteger('score');
            $table->longText('response_data');
            $table->text('feedback_given');
            $table->timestamps();

            // Historique personnel et statistiques par test.
            $table->index(['user_id', 'created_at']);
            $table->index(['test_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_test_results');
    }
};
