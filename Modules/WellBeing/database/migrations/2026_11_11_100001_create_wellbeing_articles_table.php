<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiches de prévention du module Mina Care.
 *
 * Une fiche est un contenu éducatif rattaché à une thématique de santé
 * mentale (stress, épuisement professionnel, sommeil). Seules les fiches
 * publiées sont exposées par l'API publique du module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wellbeing_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category', 20);
            $table->longText('content');
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            // Filtrage du catalogue : thématique et état de publication.
            $table->index(['category', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wellbeing_articles');
    }
};
