<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * « Awa (Gestion de stock) a modifié le produit « Riz 5 kg » : prix de
 * vente 4 200 → 4 500. » — envoyée à la gérance et à l'administrateur à
 * chaque création, modification ou suppression faite par un profil
 * Gestion de stock.
 *
 * Le texte est composé au moment de l'envoi, dans la langue de chaque
 * destinataire, à partir de données neutres.
 */
class StaffActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $action  cree | modifie | supprime | desactive | reactive | enregistre
     * @param  string  $objet   produit | categorie | fournisseur | entree | sortie
     * @param  array<int, array{0: string, 1?: string|null, 2?: string|null}>  $changements  [libellé, avant, après]
     * @param  array{0: string, 1?: array<string, mixed>}|null  $detail  [texte à traduire, paramètres]
     */
    public function __construct(
        public string $auteur,
        public string $profil,
        public string $action,
        public string $objet,
        public string $nom,
        public string $url,
        public array $changements = [],
        public ?array $detail = null,
    ) {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Base de données en direct (la cloche se remplit tout de suite, même
     * sans worker) ; le push temps réel passe par la file d'attente.
     *
     * @return array<string, string|null>
     */
    public function viaConnections(): array
    {
        return [
            'database'  => 'sync',
            'broadcast' => config('queue.default'),
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $langue = $notifiable->locale ?? null;
        $t = fn (string $texte, array $p = []) => __($texte, $p, $langue);

        $verbe = $t(match ($this->action) {
            'cree'       => 'a créé',
            'supprime'   => 'a supprimé',
            'desactive'  => 'a désactivé',
            'reactive'   => 'a réactivé',
            'enregistre' => 'a enregistré',
            default      => 'a modifié',
        });

        $quoi = $t(match ($this->objet) {
            'produit'     => 'le produit',
            'categorie'   => 'la catégorie',
            'fournisseur' => 'le fournisseur',
            'entree'      => 'l\'entrée',
            'sortie'      => 'la sortie',
            default       => $this->objet,
        });

        $message = "{$this->auteur} ({$t($this->profil)}) {$verbe} {$quoi} « {$this->nom} »";

        if ($this->changements) {
            $message .= ' : '.collect($this->changements)->map(function (array $c) use ($t) {
                $libelle = $t($c[0]);

                return array_key_exists(1, $c) && ($c[1] !== null || ($c[2] ?? null) !== null)
                    ? "{$libelle} ".($c[1] ?? '—').' → '.($c[2] ?? '—')
                    : $libelle;
            })->implode(', ');
        }

        if ($this->detail) {
            $message .= ' — '.$t($this->detail[0], $this->detail[1] ?? []);
        }

        return [
            'type'       => 'activite',
            'type_label' => $t('Activité de l\'équipe'),
            'gravite'    => $this->action === 'supprime' ? 'avertissement' : 'info',
            'message'    => $message.'.',
            'action'     => $this->action,
            'objet'      => $this->objet,
            'url'        => $this->url,
        ];
    }
}
