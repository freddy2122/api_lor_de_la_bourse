<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountOpeningRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'date_naissance' => $this->date_naissance,
            'nationalite' => $this->nationalite,
            'pays_residence' => $this->pays_residence,
            'adresse' => $this->adresse,
            'ville' => $this->ville,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
