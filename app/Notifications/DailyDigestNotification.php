<?php

namespace App\Notifications;

use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class DailyDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  array<string, mixed>  $digest */
    public function __construct(
        public string $date,
        public array $digest,
    ) {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Le résumé apparaît dans la cloche immédiatement ; seul le courriel
     * passe par la file d'attente.
     *
     * @return array<string, string|null>
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
            'mail'     => config('queue.default'),
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'resume_quotidien',
            'date'    => $this->date,
            'message' => sprintf(
                'Résumé du %s : %s de ventes, %s de marge, %d alerte(s) ouverte(s).',
                Carbon::parse($this->date)->format('d/m/Y'),
                Money::format($this->digest['ventes']),
                Money::format($this->digest['marge']),
                $this->digest['alertes'],
            ),
            'donnees' => $this->digest,

            // Chemin relatif : voir la note dans StockAlertNotification.
            'url'     => route('tableau-de-bord', absolute: false),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->digest;
        $jour = Carbon::parse($this->date)->translatedFormat('l j F Y');

        $mail = (new MailMessage)
            ->subject('['.config('naneu.shop.name').'] '.__('Résumé du :jour', ['jour' => $jour]))
            ->greeting(__('Bonjour :nom,', ['nom' => $notifiable->name]))
            ->line(__("Voici l'activité de la boutique pour le :jour.", ['jour' => $jour]))
            ->line(__('**Ventes :** :montant sur :n opération(s)', [
                'montant' => Money::format($d['ventes']),
                'n'       => $d['nombre_ventes'],
            ]))
            ->line(__('**Marge :** :montant', ['montant' => Money::format($d['marge'])]))
            ->line(__('**Achats :** :montant', ['montant' => Money::format($d['entrees'])]))
            ->line(__('**Pertes :** :montant', ['montant' => Money::format($d['pertes'])]))
            ->line(__('**Valeur du stock :** :montant', ['montant' => Money::format($d['valeur_stock'])]));

        if ($d['ruptures'] > 0 || $d['stock_bas'] > 0) {
            $mail->line(__('⚠️ :ruptures produit(s) en rupture, :bas sous le seuil de réapprovisionnement.', [
                'ruptures' => $d['ruptures'],
                'bas'      => $d['stock_bas'],
            ]));
        }

        if ($d['lots_perimes'] > 0) {
            $mail->line(__('⚠️ :n lot(s) périmé(s) encore en stock.', ['n' => $d['lots_perimes']]));
        }

        return $mail->action(__('Ouvrir le tableau de bord'), route('tableau-de-bord'));
    }
}
