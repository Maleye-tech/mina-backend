<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contacts d'urgence validés par Volkeno et ses partenaires.
 *
 * Les contacts inactifs restent en base pour conserver l'historique mais ne
 * sont jamais exposés par l'API publique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role_or_description');
            $table->string('phone_number', 40);
            $table->string('address')->nullable();
            $table->string('availability')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_contacts');
    }
};
