<?php

namespace App\Listeners;

use App\Events\StockAlertRaised;
use App\Models\User;
use App\Notifications\StockAlertNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Traduit une alerte métier en notifications utilisateur.
 *
 * Ce listener est **synchrone**, à dessein. S'il était mis en file
 * d'attente, une boutique dont le worker n'est pas lancé verrait sa cloche
 * rester désespérément vide pendant que les alertes s'accumulent — panne
 * silencieuse, la pire espèce.
 *
 * Le travail réellement coûteux (courriel, diffusion WebSocket) reste
 * différé : c'est la notification elle-même qui répartit ses canaux entre
 * exécution immédiate et file d'attente, via `viaConnections()`.
 */
class NotifyAlertRecipients
{
    public function handle(StockAlertRaised $event): void
    {
        $recipients = User::query()->alertRecipients()->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new StockAlertNotification($event->alert));

        $event->alert->forceFill(['notified_at' => now()])->save();
    }
}
