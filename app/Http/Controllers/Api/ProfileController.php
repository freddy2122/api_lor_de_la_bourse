<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Http\Resources\UserResource;

class ProfileController extends Controller
{
    // Mettre à jour les infos du profil
    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            // Ajoutez d'autres champs modifiables ici (ex: telephone)
        ]);
        $user->update($data);
        return new UserResource($user);
    }

    // Changer le mot de passe
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update([
            'password' => Hash::make($data['password']),
        ]);

        return response()->json(['message' => 'Mot de passe mis à jour avec succès.']);
    }
}
