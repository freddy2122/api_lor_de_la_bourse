<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('societes', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 64)->nullable()->index();
            $table->string('name');
            $table->string('sector')->nullable();
            $table->string('country', 64)->nullable();
            $table->string('slug')->nullable()->unique();
            $table->string('brvm_url')->nullable();
            $table->string('headquarters')->nullable();
            $table->double('market_cap_fcfa')->nullable();
            $table->double('dividend_yield_pct')->nullable();
            $table->text('description')->nullable();
            $table->json('extra')->nullable();
            $table->timestamps();
            $table->unique(['symbol'], 'societes_symbol_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('societes');
    }
};
