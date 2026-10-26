<?php

namespace App\Policies;

use App\Enums\ExitType;
use App\Enums\UserRole;
use App\Models\StockExit;
use App\Models\User;

class StockExitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccess('sorties');
    }

    /** Un ticket de caisse se rouvre depuis la caisse, même sans le menu Sorties. */
    public function view(User $user, StockExit $exit): bool
    {
        return $user->canAccess('sorties')
            || ($exit->type === ExitType::Vente && $exit->payment_method !== null && $user->canWrite('caisse'));
    }

    /** Bon de sortie (formulaire) : accès complet au menu Sorties. */
    public function create(User $user): bool
    {
        return $user->canWrite('sorties');
    }

    /** Une vente passe par la caisse ou par un bon de sortie ; les autres motifs, par un bon seulement. */
    public function createOfType(User $user, ExitType $type): bool
    {
        if ($type === ExitType::Vente && $user->canWrite('caisse')) {
            return true;
        }

        return $user->canWrite('sorties');
    }

    public function cancel(User $user, StockExit $exit): bool
    {
        return $user->hasAtLeast(UserRole::Gerant);
    }
}
