<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountOpeningRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;


class AccountOpeningController extends Controller
{
    public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'nom' => 'required|string|max:255',
        'prenom' => 'required|string|max:255',
        'date_naissance' => 'required|date',
        'nationalite' => 'required|string|max:255',
        'pays_residence' => 'required|string|max:255',
        'adresse' => 'required|string',
        'ville' => 'required|string|max:255',
        'telephone' => 'required|string|unique:account_opening_requests,telephone',
        'email' => 'required|email|unique:account_opening_requests,email',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

   
    try {
        $demande = AccountOpeningRequest::create([
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'date_naissance' => $request->date_naissance,
            'nationalite' => $request->nationalite,
            'pays_residence' => $request->pays_residence,
            'adresse' => $request->adresse,
            'ville' => $request->ville,
            'telephone' => $request->telephone,
            'email' => $request->email,
            'status' => 'en_attente_validation',
        ]);

        return response()->json([
            'message' => 'Votre dossier a été soumis avec succès.',
            'request_id' => $demande->id,
        ], 201);

    } catch (\Exception $e) {
        return response()->json(['message' => 'Une erreur est survenue lors de la soumission de votre dossier.'], 500);
    }
}
}
