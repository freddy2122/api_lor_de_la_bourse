<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Http\Resources\ArticleResource;

class ArticleController extends Controller
{
    // Liste des articles publiés
    public function index()
    {
        $articles = Article::where('status', 'publie')
            ->with('author:id,name') // Optimisation : ne charge que l'ID et le nom de l'auteur
            ->latest('published_at')
            ->get(['id', 'title', 'slug', 'category', 'published_at', 'author_id']);

        return ArticleResource::collection($articles);
    }

    // Afficher un seul article
    public function show($slug)
    {
        $article = Article::where('slug', $slug)
            ->where('status', 'publie')
            ->with('author:id,name')
            ->firstOrFail();

        return new ArticleResource($article);
    }
}
