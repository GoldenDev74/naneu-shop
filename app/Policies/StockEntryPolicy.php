<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\StockEntry;
use App\Models\User;

class StockEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccess('entrees');
    }

    public function view(User $user, StockEntry $entry): bool
    {
        return $user->canAccess('entrees');
    }

    public function create(User $user): bool
    {
        return $user->canWrite('entrees');
    }

    /** Une annulation réécrit le stock : réservée au gérant et à l'admin. */
    public function cancel(User $user, StockEntry $entry): bool
    {
        return $user->hasAtLeast(UserRole::Gerant);
    }
}
