<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    public function resetPassword(): void
    {
        $this->validate(
            [
                'token'    => ['required'],
                'email'    => ['required', 'string', 'email'],
                'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            ],
            [
                'email.required'     => 'Saisissez votre adresse e-mail.',
                'email.email'        => 'Cette adresse e-mail n\'est pas valide.',
                'password.required'  => 'Choisissez un nouveau mot de passe.',
                'password.confirmed' => 'Les deux saisies ne correspondent pas.',
            ],
        );

        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password'       => Hash::make($this->password),
                    'remember_token' => Str::random(60),

                    // Le titulaire a choisi son mot de passe : le verrou
                    // posé lors d'une création de compte n'a plus lieu d'être.
                    'must_change_password' => false,
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div>
    <h1 class="mb-1 text-center text-2xl font-extrabold text-slate-900 dark:text-white">{{ __('Nouveau mot de passe') }}</h1>
    <p class="mb-6 text-center text-sm text-slate-500 dark:text-slate-400">
        {{ __('Choisissez un mot de passe d\'au moins 10 caractères, mêlant majuscules, minuscules et chiffres.') }}
    </p>

    <form wire:submit="resetPassword" class="space-y-4">

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

        <div class="space-y-1">
            <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-200">
                {{ __('Nouveau mot de passe') }}
            </label>
            <input wire:model="password" id="password" name="password" type="password"
                   required autocomplete="new-password"
                   class="champ w-full">

            @error('password')
                <p class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ __($message) }}</p>
            @enderror
        </div>

        <div class="space-y-1">
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700 dark:text-slate-200">
                {{ __('Confirmer le mot de passe') }}
            </label>
            <input wire:model="password_confirmation" id="password_confirmation"
                   name="password_confirmation" type="password"
                   required autocomplete="new-password"
                   class="champ w-full">
        </div>

        <button type="submit" wire:loading.attr="disabled"
                class="btn-primaire w-full min-h-11">
            <span wire:loading.remove wire:target="resetPassword">{{ __('Enregistrer le mot de passe') }}</span>
            <span wire:loading wire:target="resetPassword">{{ __('Enregistrement…') }}</span>
        </button>
    </form>
</div>
