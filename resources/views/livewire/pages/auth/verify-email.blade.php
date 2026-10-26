<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('tableau-de-bord', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<div>
    <h1 class="mb-1 text-center text-2xl font-extrabold text-slate-900 dark:text-white">{{ __('Vérifiez votre adresse e-mail') }}</h1>
    <p class="mb-6 text-center text-sm text-slate-500 dark:text-slate-400">
        {{ __('Un lien de vérification vient de vous être envoyé. Ouvrez-le pour activer votre accès. Si vous ne l\'avez pas reçu, nous pouvons vous en renvoyer un.') }}
    </p>

    @if (session('status') === 'verification-link-sent')
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-800 dark:bg-emerald-900/20">
            <p class="text-sm text-emerald-800 dark:text-emerald-200">
                {{ __('Un nouveau lien de vérification a été envoyé à votre adresse.') }}
            </p>
        </div>
    @endif

    <div class="space-y-3">
        <button wire:click="sendVerification" wire:loading.attr="disabled"
                class="btn-primaire w-full min-h-11">
            <span wire:loading.remove wire:target="sendVerification">{{ __('Renvoyer le lien') }}</span>
            <span wire:loading wire:target="sendVerification">{{ __('Envoi…') }}</span>
        </button>

        <form method="POST" action="{{ route('deconnexion') }}">
            @csrf
            <button type="submit"
                    class="w-full text-center text-sm text-slate-500 hover:underline dark:text-slate-400">
                {{ __('Se déconnecter') }}
            </button>
        </form>
    </div>
</div>
