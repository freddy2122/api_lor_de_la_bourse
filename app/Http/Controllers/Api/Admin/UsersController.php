<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    // GET /api/admin/users?q=search&status=actif|bloque|en_attente_kyc&per_page=20&page=1
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $query = User::query()->where('role', 'client');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%$q%")
                    ->orWhere('email', 'like', "%$q%");
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        // Simple pagination parameters
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $users = $query->latest('id')->paginate($perPage);

        return UserResource::collection($users);
    }

    // PATCH /api/admin/users/{user}/status { status: actif|bloque|en_attente_kyc }
    public function updateStatus(Request $request, User $user)
    {
        // Optionnel: empêcher de modifier un admin
        if ($user->role !== 'client') {
            return response()->json(['message' => 'Modification de statut réservée aux clients.'], 403);
        }

        $data = $request->validate([
            'status' => 'required|in:actif,bloque,en_attente_kyc',
        ]);

        $user->status = $data['status'];
        $user->save();

        return response()->json([
            'message' => 'Statut mis à jour.',
            'data' => new UserResource($user),
        ]);
    }
}
