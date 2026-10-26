<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('tableau-de-bord', absolute: false), navigate: true);
    }
}; ?>

<div>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <h1 class="mb-1 text-center text-2xl font-extrabold text-slate-900 dark:text-white">{{ __('Connexion') }}</h1>
    <p class="mb-6 text-center text-sm text-slate-500 dark:text-slate-400">
        {{ __('Identifiez-vous pour accéder à la gestion du stock.') }}
    </p>

    <form wire:submit="login" class="space-y-4">

        <div class="space-y-1">
            <label for="login" class="block text-sm font-medium text-slate-700 dark:text-slate-200">
                {{ __('E-mail ou nom d\'utilisateur') }}
            </label>
            <input wire:model="form.login" id="login" name="login" type="text"
                   required autofocus autocomplete="username" autocapitalize="none" spellcheck="false"
                   class="champ w-full">

            @error('form.login')
                <p class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ __($message) }}</p>
            @enderror
        </div>

        <div class="space-y-1">
            <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-200">
                {{ __('Mot de passe') }}
            </label>
            <input wire:model="form.password" id="password" name="password" type="password"
                   required autocomplete="current-password"
                   class="champ w-full">

            @error('form.password')
                <p class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ __($message) }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between">
            <label for="remember" class="sans-tuile inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input wire:model="form.remember" id="remember" name="remember" type="checkbox"
                       class="case">
                {{ __('Se souvenir de moi') }}
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" wire:navigate
                   class="text-sm font-medium text-marque-600 hover:underline dark:text-marque-400">
                    {{ __('Mot de passe oublié ?') }}
                </a>
            @endif
        </div>

        <button type="submit" wire:loading.attr="disabled"
                class="btn-primaire min-h-11 w-full">
            <span wire:loading.remove wire:target="login">{{ __('Se connecter') }}</span>
            <span wire:loading wire:target="login">{{ __('Connexion…') }}</span>
        </button>
    </form>
</div>
