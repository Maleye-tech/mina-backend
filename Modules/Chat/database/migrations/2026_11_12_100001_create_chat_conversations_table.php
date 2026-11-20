<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversations directes du module Chat.
 *
 * Une conversation relie exactement deux utilisateurs (module 1-1 entre un
 * étudiant et un professionnel de santé mentale). Les identifiants sont
 * toujours stockés dans un ordre canonique (participant_one < participant_two)
 * afin de garantir une unique ligne par paire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_one_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('participant_two_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            // Une seule conversation par paire d'utilisateurs.
            $table->unique(['participant_one_id', 'participant_two_id']);
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
