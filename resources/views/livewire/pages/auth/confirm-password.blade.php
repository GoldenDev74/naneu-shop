<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /** Redemande le mot de passe avant une opération sensible. */
    public function confirmPassword(): void
    {
        $this->validate(
            ['password' => ['required', 'string']],
            ['password.required' => 'Saisissez votre mot de passe.'],
        );

        if (! Auth::guard('web')->validate([
            'email'    => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => 'Ce mot de passe ne correspond pas à celui enregistré.',
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('tableau-de-bord', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h1 class="mb-1 text-center text-2xl font-extrabold text-slate-900 dark:text-white">{{ __('Confirmation requise') }}</h1>
    <p class="mb-6 text-center text-sm text-slate-500 dark:text-slate-400">
        {{ __('Vous accédez à une zone sensible de l\'application. Confirmez votre mot de passe pour continuer.') }}
    </p>

    <form wire:submit="confirmPassword" class="space-y-4">

        <div class="space-y-1">
            <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-200">
                {{ __('Mot de passe') }}
            </label>
            <input wire:model="password" id="password" name="password" type="password"
                   required autocomplete="current-password" autofocus
                   class="champ w-full">

            @error('password')
                <p class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ __($message) }}</p>
            @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled"
                class="btn-primaire w-full min-h-11">
            <span wire:loading.remove wire:target="confirmPassword">{{ __('Confirmer') }}</span>
            <span wire:loading wire:target="confirmPassword">{{ __('Vérification…') }}</span>
        </button>
    </form>
</div>
