<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Demande rejetée</title>
</head>
<body>
    <h2>Bonjour {{ $accountOpeningRequest->prenom }} {{ $accountOpeningRequest->nom }},</h2>
    <p>Nous regrettons de vous informer que votre demande d’ouverture de compte a été <strong>rejetée</strong>.</p>
    <p><strong>Raison :</strong> {{ $reason }}</p>
    <p>Si vous pensez qu’il s’agit d’une erreur, merci de nous contacter.</p>
    <br>
    <p>Cordialement,</p>
    <p>L’équipe support</p>
</body>
</html>
