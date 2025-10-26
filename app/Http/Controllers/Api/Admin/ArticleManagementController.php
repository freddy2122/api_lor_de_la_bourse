<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArticleManagementController extends Controller
{
    public function index()
    {
        return Article::with('author:id,name')->latest()->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'required|string',
            'status' => 'required|in:brouillon,publie',
        ]);

        $article = Article::create([
            'title' => $request->title,
            'slug' => Str::slug($request->title) . '-' . uniqid(),
            'content' => $request->content,
            'category' => $request->category,
            'status' => $request->status,
            'author_id' => auth()->id(),
            'published_at' => $request->status === 'publie' ? now() : null,
        ]);

        return response()->json($article, 201);
    }

    public function update(Request $request, Article $article)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'required|string',
            'status' => 'required|in:brouillon,publie',
        ]);

        $article->update([
            'title' => $request->title,
            'slug' => Str::slug($request->title) . '-' . $article->id, // Garder un slug stable
            'content' => $request->content,
            'category' => $request->category,
            'status' => $request->status,
            'published_at' => ($article->status !== 'publie' && $request->status === 'publie') ? now() : $article->published_at,
        ]);

        return response()->json($article);
    }

    public function destroy(Article $article)
    {
        $article->delete();
        return response()->json(null, 204);
    }

    // Publier un article: passe le statut à 'publie' et fixe published_at si absent
    public function publish(Article $article)
    {
        $article->status = 'publie';
        if (!$article->published_at) {
            $article->published_at = now();
        }
        $article->save();
        return response()->json($article);
    }

    // Dépublier un article: passe le statut à 'brouillon' (conserve published_at pour l'historique)
    public function unpublish(Article $article)
    {
        $article->status = 'brouillon';
        $article->save();
        return response()->json($article);
    }
}
