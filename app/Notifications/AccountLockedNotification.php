<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * « Le compte de Awa (awa.kossi) est verrouillé après 5 échecs de
 * connexion, jusqu'à 14:35. » — envoyée à la gérance : une suite d'échecs
 * peut être un oubli… ou quelqu'un qui essaie des mots de passe.
 */
class AccountLockedNotification extends Notification
{
    use Queueable;

    public function __construct(public User $compte)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $langue = $notifiable->locale ?? null;

        return [
            'type'       => 'securite',
            'type_label' => __('Sécurité', [], $langue),
            'gravite'    => 'avertissement',
            'message'    => __('Le compte de :nom (:identifiant) est verrouillé après :n échecs de connexion, jusqu\'à :heure.', [
                'nom'         => $this->compte->name,
                'identifiant' => $this->compte->username,
                'n'           => (int) config('naneu.security.max_failed_logins'),
                'heure'       => $this->compte->locked_until?->format('H:i'),
            ], $langue),
            'url'        => route('utilisateurs.index', absolute: false),
        ];
    }
}
