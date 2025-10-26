<x-mail::message>
# Bienvenue chez L'Or de la Bourse, {{ $user->name }} !

Votre compte a été validé avec succès par notre équipe. Vous pouvez désormais accéder à votre espace client pour commencer à investir.

Voici vos identifiants de connexion :

**Identifiant (Email) :** {{ $user->email }}
**Mot de passe temporaire :** `{{ $temporaryPassword }}`

Nous vous recommandons vivement de changer ce mot de passe lors de votre première connexion depuis la section "Paramètres" de votre tableau de bord.

<x-mail::button :url="config('app.frontend_url') . '/login'">
Accéder à mon compte
</x-mail::button>

Merci de votre confiance,  

L'équipe de {{ config('app.name') }}
</x-mail::message>
