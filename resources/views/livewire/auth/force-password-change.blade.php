<div>
    <h1 class="mb-1 text-center text-2xl font-extrabold text-slate-900 dark:text-white">{{ __('Choisissez votre mot de passe') }}</h1>
    <p class="mb-6 text-center text-sm text-slate-500 dark:text-slate-400">
        {{ __('Votre mot de passe actuel est provisoire. Remplacez-le pour accéder à l\'application.') }}
    </p>

    <form wire:submit="changer" class="space-y-4">

        <div class="space-y-1">
            <label for="current_password" class="block text-sm font-medium text-slate-700 dark:text-slate-200">
                {{ __('Mot de passe provisoire') }}
            </label>
            <input wire:model="current_password" id="current_password" type="password"
                   required autofocus autocomplete="current-password"
                   class="champ w-full">

            @error('current_password')
                <p class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ __($message) }}</p>
            @enderror
        </div>

        <div class="space-y-1">
            <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-200">
                {{ __('Nouveau mot de passe') }}
            </label>
            <input wire:model="password" id="password" type="password"
                   required autocomplete="new-password"
                   class="champ w-full">

            <p class="text-xs text-slate-400">
                {{ __('10 caractères minimum, avec majuscules, minuscules et chiffres. Les mots de passe apparus dans des fuites connues sont refusés.') }}
            </p>

            @error('password')
                <p class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ __($message) }}</p>
            @enderror
        </div>

        <div class="space-y-1">
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700 dark:text-slate-200">
                {{ __('Confirmer le nouveau mot de passe') }}
            </label>
            <input wire:model="password_confirmation" id="password_confirmation" type="password"
                   required autocomplete="new-password"
                   class="champ w-full">
        </div>

        <button type="submit" wire:loading.attr="disabled"
                class="btn-primaire w-full min-h-11">
            <span wire:loading.remove wire:target="changer">{{ __('Enregistrer et continuer') }}</span>
            <span wire:loading wire:target="changer">{{ __('Enregistrement…') }}</span>
        </button>
    </form>

    <form method="POST" action="{{ route('deconnexion') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm text-slate-500 hover:underline dark:text-slate-400">
            {{ __('Se déconnecter') }}
        </button>
    </form>
</div>
