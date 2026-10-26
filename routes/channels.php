<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Canaux de diffusion
|--------------------------------------------------------------------------
|
| Tous les canaux sont privés : une donnée de stock ou une alerte ne doit
| jamais transiter sur un canal public. L'autorisation est rejouée par
| Reverb à chaque souscription.
|
*/

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id && $user->is_active;
});

/** Mouvements de stock : visibles par tout compte actif. */
Broadcast::channel('naneu.stock', function (User $user) {
    return $user->is_active;
});

/** Alertes : réservées aux profils qui peuvent y répondre. */
Broadcast::channel('naneu.alertes', function (User $user) {
    return $user->is_active && $user->hasAtLeast(UserRole::Magasinier);
});

/** Canal d'administration : évènements de comptes et de paramètres. */
Broadcast::channel('naneu.admin', function (User $user) {
    return $user->is_active && $user->isAdmin();
});
