<?php

return [

  'paths' => ['api/*'],

'allowed_methods' => ['*'],

// Utilise la variable d'environnement FRONTEND_URL pour définir l'origin autorisé
// Exemple: FRONTEND_URL=http://localhost:5174 en dev, https://app.mondomaine.com en prod
'allowed_origins' => [rtrim(env('FRONTEND_URL', 'http://192.168.188.3:5173'), '/')],

'allowed_origins_patterns' => [],

'allowed_headers' => ['*'],

'exposed_headers' => [],

'max_age' => 0,

// Si tu utilises les tokens (pas les cookies Sanctum), mets à false
'supports_credentials' => false,

];
