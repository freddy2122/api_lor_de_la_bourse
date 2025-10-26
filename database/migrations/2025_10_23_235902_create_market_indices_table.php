<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_indices', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('last_value', 18, 4)->nullable();
            $table->decimal('change_abs', 18, 4)->nullable();
            $table->decimal('change_pct', 8, 4)->nullable();
            $table->timestamp('market_timestamp')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_indices');
    }
};
