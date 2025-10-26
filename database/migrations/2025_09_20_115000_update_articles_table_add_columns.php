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
        Schema::table('articles', function (Blueprint $table) {
            $table->string('title')->after('id');
            $table->string('slug')->unique()->after('title');
            $table->longText('content')->after('slug');
            $table->string('category')->after('content');
            // Status de publication (brouillon/publie)
            $table->string('status')->default('brouillon')->after('category');
            $table->timestamp('published_at')->nullable()->after('status');
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete()->after('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('author_id');
            $table->dropColumn(['title', 'slug', 'content', 'category', 'status', 'published_at']);
        });
    }
};

