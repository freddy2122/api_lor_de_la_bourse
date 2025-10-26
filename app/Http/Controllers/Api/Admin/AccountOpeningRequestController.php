<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AccountRejectedMail;
use App\Models\AccountOpeningRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\WelcomeEmail; // <-- Nous créerons cet email juste après
use Illuminate\Support\Facades\Log;
use App\Http\Resources\AccountOpeningRequestResource;

class AccountOpeningRequestController extends Controller
{
    public function index(Request $request)
    {
        // 1. Récupérer les demandes en attente, ordonnées par date.
        $pendingRequests = AccountOpeningRequest::where('status', 'en_attente_validation')
            ->orderBy('created_at', 'asc')
            ->get();

        return AccountOpeningRequestResource::collection($pendingRequests);
    }


    public function show(AccountOpeningRequest $request)
    {
        // if (auth()->user()->cannot('view', $request)) {
        //     return response()->json(['message' => 'Accès non autorisé.'], 403);
        // }

        // Ici, vous renverriez TOUTES les informations de la demande, y compris les chemins des fichiers.
        // Pour l'instant, on la laisse vide.
        return new AccountOpeningRequestResource($request);
    }
    public function approve(AccountOpeningRequest $accountOpeningRequest)
    {
        // Log du modèle pour debug
        Log::info('Tentative d\'approbation', [
            'id' => $accountOpeningRequest->id,
            'status' => $accountOpeningRequest->status,
            'nom' => $accountOpeningRequest->nom,
            'prenom' => $accountOpeningRequest->prenom,
            'email' => $accountOpeningRequest->email,
        ]);
        

        if ($accountOpeningRequest->status !== 'en_attente_validation') {
            return response()->json([
                'message' => "Impossible d'approuver. Statut actuel : {$accountOpeningRequest->status}",
                'data' => [
                    'id' => $accountOpeningRequest->id,
                    'status' => $accountOpeningRequest->status,
                ]
            ], 409);
        }

        // Générer un mot de passe temporaire
        $temporaryPassword = Str::random(12);

        // Créer l'utilisateur lié
        $user = User::create([
            'name' => $accountOpeningRequest->prenom . ' ' . $accountOpeningRequest->nom,
            'email' => $accountOpeningRequest->email,
            'password' => Hash::make($temporaryPassword),
            'role' => 'client',
        ]);

        // Mettre à jour la demande
        $accountOpeningRequest->status = 'validee';
        $accountOpeningRequest->save();

        // Envoyer l'email de bienvenue
        try {
            Mail::to($user->email)->send(new WelcomeEmail($user, $temporaryPassword));
        } catch (\Exception $e) {
            Log::error('Erreur envoi email WelcomeEmail: ' . $e->getMessage());
            return response()->json([
                'message' => 'Compte validé, mais échec d’envoi de l’email.',
                'error'   => $e->getMessage(),
                'data'    => [
                    'user_id' => $user->id,
                    'request_id' => $accountOpeningRequest->id,
                ],
            ], 500);
        }

        return response()->json([
            'message' => 'Compte validé avec succès. Email envoyé.',
            'data' => [
                'user_id' => $user->id,
                'request_id' => $accountOpeningRequest->id,
            ],
        ]);
    }

    public function reject(AccountOpeningRequest $accountOpeningRequest, Request $httpRequest)
    {
        // Log du modèle pour debug
        Log::info('Tentative de rejet', $accountOpeningRequest->toArray());

        if ($accountOpeningRequest->status !== 'en_attente_validation') {
            return response()->json([
                'message' => "Impossible de rejeter. Statut actuel : {$accountOpeningRequest->status}",
                'data' => [
                    'id' => $accountOpeningRequest->id,
                    'status' => $accountOpeningRequest->status,
                ]
            ], 409);
        }

        $reason = $httpRequest->input('reason', 'Raison non spécifiée');

        $accountOpeningRequest->status = 'rejete';
        $accountOpeningRequest->rejection_reason = $reason;
        $accountOpeningRequest->save();

        // Envoyer un mail de notification au client
        try {
            Mail::to($accountOpeningRequest->email)
                ->send(new \App\Mail\AccountRejectedMail($accountOpeningRequest,$reason));
        } catch (\Exception $e) {
            Log::error('Erreur envoi email AccountRejectedMail: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'La demande a été rejetée.',
            'data' => [
                'request_id' => $accountOpeningRequest->id,
                'reason' => $reason,
            ],
        ]);
    }

}
