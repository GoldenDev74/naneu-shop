<?php

/*
|--------------------------------------------------------------------------
| Messages du système de mot de passe
|--------------------------------------------------------------------------
|
| Laravel 11 et 12 ne publient plus de répertoire lang/. Sans ces fichiers,
| `__('passwords.sent')` renvoie la clé brute : l'utilisateur lisait
| littéralement « passwords.sent » à l'écran.
|
*/

return [
    'reset'     => 'Votre mot de passe a été réinitialisé.',
    'sent'      => 'Un lien de réinitialisation vient de vous être envoyé par e-mail.',
    'throttled' => 'Veuillez patienter avant de réessayer.',
    'token'     => 'Ce lien de réinitialisation est invalide ou a expiré.',
    'user'      => 'Aucun compte actif ne correspond à cette adresse e-mail.',
];
