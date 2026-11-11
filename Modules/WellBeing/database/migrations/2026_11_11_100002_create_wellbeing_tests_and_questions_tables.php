<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tests d'auto-évaluation et leurs questions.
 *
 * Les options d'une question sont stockées dans une colonne JSON afin de
 * conserver, pour chaque choix, son libellé et les points qui lui sont
 * associés. Les points ne sont jamais exposés par l'API : le calcul du
 * score reste côté serveur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tests', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 20);
            $table->timestamps();

            $table->index('type');
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained('tests')->cascadeOnDelete();
            $table->text('question_text');

            /*
             * Structure attendue :
             * [
             *     ['key' => 'a', 'label' => 'Jamais', 'points' => 0],
             *     ['key' => 'b', 'label' => 'Parfois', 'points' => 1],
             * ]
             */
            $table->json('options');
            $table->timestamps();

            $table->index('test_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
        Schema::dropIfExists('tests');
    }
};
