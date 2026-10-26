<?php

namespace App\Livewire\Concerns;

/**
 * Les messages sont écrits en français dans le code et traduits ici, au
 * moment de l'envoi, dans la langue de l'utilisateur (lang/en.json).
 */
trait InteractsWithToasts
{
    protected function toastSucces(string $message, ?string $titre = null): void
    {
        $this->dispatch('toast', type: 'succes', message: __($message), titre: $titre ? __($titre) : null);
    }

    protected function toastErreur(string $message, ?string $titre = null): void
    {
        $this->dispatch('toast', type: 'erreur', message: __($message), titre: __($titre ?? 'Opération refusée'));
    }

    protected function toastInfo(string $message, ?string $titre = null): void
    {
        $this->dispatch('toast', type: 'info', message: __($message), titre: $titre ? __($titre) : null);
    }
}
