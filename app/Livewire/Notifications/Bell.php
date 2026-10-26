<?php

namespace App\Livewire\Notifications;

use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Cloche de notifications.
 *
 * Deux sources la réveillent : le push Reverb (instantané) et, en secours,
 * un wire:poll lent. Le poll n'est pas redondant — il couvre le cas où le
 * WebSocket est coupé par un proxy ou une coupure réseau, situation banale
 * sur une connexion de boutique.
 */
class Bell extends Component
{
    public bool $ouvert = false;

    #[On('notification-recue')]
    #[On('alerte-recue')]
    #[On('alerte-resolue')]
    public function rafraichir(): void
    {
        // Le simple fait de traiter l'évènement relance le rendu.
    }

    public function basculer(): void
    {
        $this->ouvert = ! $this->ouvert;
    }

    public function marquerLue(string $id): void
    {
        auth()->user()->notifications()->whereKey($id)->first()?->markAsRead();
    }

    public function toutMarquerLu(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    /**
     * Ramène le lien d'une notification à un chemin relatif.
     *
     * Les notifications déjà en base peuvent contenir une URL absolue
     * figée à leur création (APP_URL au moment où la console tournait).
     * Suivre un tel lien ferait changer d'origine au navigateur — donc
     * perdre le cookie de session et atterrir sur l'écran de connexion.
     * On ne conserve que le chemin, qui reste valable quelle que soit
     * l'adresse par laquelle la boutique accède à l'application.
     */
    public function lien(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $chemin = parse_url($url, PHP_URL_PATH);

        if (! is_string($chemin) || $chemin === '') {
            return null;
        }

        $requete = parse_url($url, PHP_URL_QUERY);

        return '/'.ltrim($chemin, '/').($requete ? '?'.$requete : '');
    }

    /** @return Collection<int, DatabaseNotification> */
    public function getRecentesProperty(): Collection
    {
        return auth()->user()
            ->notifications()
            ->latest()
            ->limit(12)
            ->get();
    }

    public function getNonLuesProperty(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function render(): View
    {
        return view('livewire.notifications.bell', [
            'notifications' => $this->recentes,
            'nonLues'       => $this->nonLues,
        ]);
    }
}
