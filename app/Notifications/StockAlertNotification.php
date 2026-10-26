<?php

namespace App\Notifications;

use App\Enums\AlertSeverity;
use App\Models\StockAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification persistée en base (cloche) et poussée en temps réel.
 * Le courriel n'est envoyé que pour les alertes critiques : une boîte
 * saturée finit ignorée, et c'est précisément ce qu'il ne faut pas pour
 * une rupture ou un produit périmé.
 */
class StockAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public StockAlert $alert)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if ($this->alert->severity === AlertSeverity::Critique && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Répartition des canaux entre exécution immédiate et file d'attente.
     *
     * `database` tourne sur la connexion `sync` : la cloche se remplit à
     * l'instant où l'alerte s'ouvre, même si aucun worker n'est lancé.
     * C'est un simple INSERT, le coût est négligeable.
     *
     * `broadcast` et `mail` partent en file d'attente : ils sortent du
     * serveur (WebSocket, SMTP) et n'ont rien à faire dans le temps de
     * réponse d'une vente.
     *
     * @return array<string, string|null>
     */
    public function viaConnections(): array
    {
        return [
            'database'  => 'sync',
            'broadcast' => config('queue.default'),
            'mail'      => config('queue.default'),
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $this->alert->loadMissing(['product', 'batch']);

        return [
            'alerte_id'  => $this->alert->id,
            'type'       => $this->alert->type->value,
            'type_label' => $this->alert->type->label(),
            'gravite'    => $this->alert->severity->value,
            'message'    => $this->alert->message,
            'produit_id' => $this->alert->product_id,
            'produit'    => $this->alert->product?->name,
            'sku'        => $this->alert->product?->sku,
            'lot'        => $this->alert->batch?->batch_number,

            // Chemin relatif, et non URL absolue. Une notification créée
            // depuis la console ou le planificateur figerait sinon APP_URL
            // dans sa charge utile : en cliquant, l'utilisateur changerait
            // d'origine (localhost vs 127.0.0.1, ou un autre port), perdrait
            // son cookie de session et retomberait sur l'écran de connexion.
            'url'        => route('alertes.index', absolute: false),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $shop = config('naneu.shop.name');

        return (new MailMessage)
            ->subject("[{$shop}] ".$this->alert->type->label().' — '.$this->alert->product?->name)
            ->greeting(__('Bonjour :nom,', ['nom' => $notifiable->name]))
            ->line($this->alert->message)
            ->action(__('Voir les alertes'), route('alertes.index'))
            ->line(__('Vous recevez ce message car vous gérez le stock de :boutique.', ['boutique' => $shop]));
    }
}
