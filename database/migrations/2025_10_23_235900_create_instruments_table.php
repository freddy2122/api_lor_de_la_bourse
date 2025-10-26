<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->string('ticker')->unique();
            $table->string('name');
            $table->string('sector')->nullable();
            $table->string('isin')->nullable();
            $table->string('currency')->default('XOF');
            $table->string('status')->default('listed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instruments');
    }
};
