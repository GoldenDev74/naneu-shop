<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Transmet les identifiants d'un compte fraîchement créé.
 *
 * Le mot de passe provisoire ne transite que par ce courriel et n'est
 * jamais enregistré en clair : il n'existe en mémoire que le temps de
 * l'envoi. Le compte est marqué « doit changer son mot de passe », donc
 * ce secret n'a qu'une seule utilisation valable.
 */
class AccountCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $motDePasseProvisoire,
        private readonly string $creePar,
    ) {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $boutique = config('naneu.shop.name');

        return (new MailMessage)
            ->subject(__('Votre accès à :boutique', ['boutique' => $boutique]))
            ->greeting(__('Bonjour :nom,', ['nom' => $notifiable->name]))
            ->line(__(":auteur vous a créé un compte sur l'application de gestion de stock de :boutique.", [
                'auteur'   => $this->creePar,
                'boutique' => $boutique,
            ]))
            ->line(__('**Nom d\'utilisateur :** :identifiant (ou votre adresse :email)', ['identifiant' => $notifiable->username, 'email' => $notifiable->email]))
            ->line(__('**Mot de passe provisoire :** :mdp', ['mdp' => $this->motDePasseProvisoire]))
            ->action(__('Se connecter'), route('login'))
            ->line(__('Ce mot de passe est provisoire : il vous sera demandé d\'en choisir un nouveau à la première connexion.'))
            ->line(__('Si vous n\'attendiez pas ce message, prévenez immédiatement un administrateur.'));
    }
}
