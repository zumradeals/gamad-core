<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La référence canonique est dans le registre Core, potentiellement
        // dans un autre schéma : aucune fausse clé étrangère n'est déclarée.
        Schema::create('reseau_comptes_identites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('identite_reference', 255)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseau_comptes_identites');
    }
};
