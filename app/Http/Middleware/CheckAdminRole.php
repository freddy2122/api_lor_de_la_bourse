<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // On vérifie si l'utilisateur est connecté ET si son rôle est 'admin'
        if ($request->user() && $request->user()->role === 'admin') {
            // Si c'est le cas, on laisse la requête continuer
            return $next($request);
        }

        // Sinon, on bloque la requête avec une erreur 403 "Accès Interdit"
        return response()->json(['message' => 'Accès non autorisé.'], 403);
    }
}
