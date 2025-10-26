<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\UserResource;

class AuthController extends Controller
{


    // Vérification email via OTP


    // Connexion
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Identifiants incorrects'
            ], 401);
        }

        // Supprimer les anciens tokens si tu veux éviter l’accumulation
        $user->tokens()->delete();

        // Générer un nouveau token
        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }


    // Déconnexion (révocation du token)
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    // Exemple d’un endpoint protégé
    public function me(Request $request)
    {
        return new UserResource($request->user());
    }


    // Demande de réinitialisation
    public function forgot(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::where('email', $request->email)->first();

        // Générer un OTP (6 chiffres)
        $otp = rand(100000, 999999);

        // Sauvegarder dans la DB
        $user->otp = $otp;
        $user->otp_expires_at = now()->addMinutes(15); // expiration 15 min
        $user->save();

        // Envoyer l'email
        Mail::raw("Votre code OTP de réinitialisation est : $otp (valide 15 minutes)", function ($message) use ($user) {
            $message->to($user->email)
                ->subject('Réinitialisation du mot de passe');
        });

        return response()->json(['message' => 'Un code OTP a été envoyé à votre adresse email.']);
    }


    // Réinitialisation mot de passe
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email|exists:users,email',
            'otp'      => 'required|numeric',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::where('email', $request->email)->first();

        // Vérifier OTP
        if ($user->otp !== $request->otp) {
            return response()->json(['message' => 'Code OTP invalide'], 400);
        }

        // Vérifier expiration OTP
        if ($user->otp_expires_at && $user->otp_expires_at->isPast()) {
            return response()->json(['message' => 'Le code OTP a expiré'], 400);
        }

        // Mettre à jour le mot de passe
        $user->password = Hash::make($request->password);
        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();

        return response()->json(['message' => 'Mot de passe réinitialisé avec succès']);
    }
}
