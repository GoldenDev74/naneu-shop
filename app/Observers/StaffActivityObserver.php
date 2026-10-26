<?php

namespace App\Observers;

use App\Services\StaffActivityNotifier;
use Illuminate\Database\Eloquent\Model;

/**
 * Produits, catégories, fournisseurs : chaque création, modification ou
 * suppression faite par la gestion de stock est notifiée à la gérance.
 */
class StaffActivityObserver
{
    public function __construct(private StaffActivityNotifier $notifier)
    {
    }

    public function created(Model $modele): void
    {
        $this->notifier->modele('cree', $modele, auth()->user());
    }

    public function updated(Model $modele): void
    {
        $this->notifier->modele('modifie', $modele, auth()->user());
    }

    public function deleted(Model $modele): void
    {
        $this->notifier->modele('supprime', $modele, auth()->user());
    }
}
