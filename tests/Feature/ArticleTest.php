<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_published_articles_only(): void
    {
        $author = User::factory()->create();
        // Un article publié
        $published = Article::create([
            'title' => 'Titre Publié',
            'slug' => 'titre-publie-1',
            'content' => 'Contenu',
            'category' => 'news',
            'status' => 'publie',
            'author_id' => $author->id,
            'published_at' => now(),
        ]);
        // Un brouillon
        $draft = Article::create([
            'title' => 'Brouillon',
            'slug' => 'brouillon-1',
            'content' => 'Contenu',
            'category' => 'news',
            'status' => 'brouillon',
            'author_id' => $author->id,
        ]);

        $res = $this->getJson('/api/articles');
        $res->assertOk()
            ->assertJsonFragment(['slug' => $published->slug])
            ->assertJsonMissing(['slug' => $draft->slug]);
    }

    public function test_show_published_article_by_slug(): void
    {
        $author = User::factory()->create();
        $published = Article::create([
            'title' => 'Titre Publié',
            'slug' => 'titre-publie-2',
            'content' => 'Contenu',
            'category' => 'news',
            'status' => 'publie',
            'author_id' => $author->id,
            'published_at' => now(),
        ]);

        $this->getJson('/api/articles/'.$published->slug)
            ->assertOk()
            ->assertJsonFragment(['slug' => $published->slug]);
    }
}
