<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_opening_requests', function (Blueprint $table) {
            $table->id();

            // --- Informations Personnelles (Étape 1) ---
            $table->string('nom');
            $table->string('prenom');
            $table->date('date_naissance');
            $table->string('nationalite');
            $table->string('pays_residence');
            $table->text('adresse');
            $table->string('ville');
            $table->string('telephone')->unique();
            $table->string('email')->unique();
            // --- Statut de la demande (pour le back-office) ---
            $table->string('status')->default('en_attente_validation'); // 'en_attente_validation', 'valide', 'rejete'
            $table->text('rejection_reason')->nullable(); // En cas de rejet

            $table->timestamps(); // created_at et updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_opening_requests');
    }
};
