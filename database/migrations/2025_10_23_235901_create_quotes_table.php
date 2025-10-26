<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->decimal('last_price', 18, 4)->nullable();
            $table->decimal('open', 18, 4)->nullable();
            $table->decimal('high', 18, 4)->nullable();
            $table->decimal('low', 18, 4)->nullable();
            $table->decimal('previous_close', 18, 4)->nullable();
            $table->decimal('change_abs', 18, 4)->nullable();
            $table->decimal('change_pct', 8, 4)->nullable();
            $table->bigInteger('volume')->nullable();
            $table->decimal('value_traded', 24, 4)->nullable();
            $table->timestamp('market_timestamp')->nullable();
            $table->timestamps();

            $table->unique(['instrument_id', 'market_timestamp']);
            $table->index(['instrument_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
