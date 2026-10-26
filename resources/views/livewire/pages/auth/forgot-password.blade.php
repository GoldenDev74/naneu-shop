<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /** Envoie un lien de réinitialisation à l'adresse saisie. */
    public function sendPasswordResetLink(): void
    {
        $this->validate(
            ['email' => ['required', 'string', 'email']],
            [
                'email.required' => 'Saisissez votre adresse e-mail.',
                'email.email'    => 'Cette adresse e-mail n\'est pas valide.',
            ],
        );

        $status = Password::sendResetLink($this->only('email'));

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    <h1 class="mb-1 text-center text-2xl font-extrabold text-slate-900 dark:text-white">{{ __('Mot de passe oublié') }}</h1>
    <p class="mb-6 text-center text-sm text-slate-500 dark:text-slate-400">
        {{ __('Indiquez votre adresse e-mail : vous recevrez un lien pour choisir un nouveau mot de passe.') }}
    </p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="space-y-4">

        <div class="space-y-1">
            <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-200">
                {{ __('Adresse e-mail') }}
            </label>
            <input wire:model="email" id="email" name="email" type="email"
                   required autofocus autocomplete="username"
                   class="champ w-full">

            @error('email')
                <p class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ __($message) }}</p>
            @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled"
                class="btn-primaire w-full min-h-11">
            <span wire:loading.remove wire:target="sendPasswordResetLink">{{ __('Envoyer le lien') }}</span>
            <span wire:loading wire:target="sendPasswordResetLink">{{ __('Envoi…') }}</span>
        </button>
    </form>

    <p class="mt-4 text-center">
        <a href="{{ route('login') }}" wire:navigate
           class="text-sm text-slate-500 hover:underline dark:text-slate-400">
            {{ __('Revenir à la connexion') }}
        </a>
    </p>
</div>
