<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Jobs\RefreshBrvmData;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:regenerate-token {email=admin@example.com}', function (string $email) {
    /** @var \App\Models\User|null $admin */
    $admin = User::where('email', $email)->where('role', 'admin')->first();
    if (!$admin) {
        $this->error("Aucun utilisateur admin trouvé avec l'email: {$email}");
        return 1;
    }

    // Révoque les anciens tokens
    $admin->tokens()->delete();

    // Crée un nouveau token
    $token = $admin->createToken('admin_token')->plainTextToken;
    $this->info("Nouveau token pour {$email} : {$token}");
    return 0;
})->purpose("Regénère et affiche un token d'API pour un administrateur donné");

Artisan::command('brvm:refresh', function () {
    if (config('services.market.source') !== 'scraper') {
        $this->warn("services.market.source != scraper, rien n'est déclenché");
        return 0;
    }
    RefreshBrvmData::dispatch();
    $this->info('Job RefreshBrvmData dispatché.');
    return 0;
})->purpose("Déclenche le rafraîchissement des données BRVM (scraper)");
