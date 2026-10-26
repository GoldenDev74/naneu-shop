<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-5">

        <div>
            <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">{{ __('Mon profil') }}</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                {{ __('Vos informations et votre mot de passe.') }}
            </p>
        </div>

        <x-form.card :title="__('Informations personnelles')">
            <livewire:profile.update-profile-information-form />
        </x-form.card>

        <x-form.card :title="__('Mot de passe')"
                     :description="__('Choisissez un mot de passe d\'au moins 10 caractères, mêlant majuscules, minuscules et chiffres.')">
            <livewire:profile.update-password-form />
        </x-form.card>

        {{--
            Pas de suppression de compte en libre-service : les accès à
            Naneu Shop sont gérés par un administrateur, et un compte
            supprimé par son titulaire emporterait la traçabilité des
            mouvements qu'il a saisis.
        --}}
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/50">
            <p class="text-sm text-slate-600 dark:text-slate-300">
                <strong class="font-medium text-slate-900 dark:text-white">{{ __('Votre rôle :') }}</strong>
                {{ auth()->user()->role->label() }} — {{ auth()->user()->role->description() }}
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {{ __('Pour toute modification de rôle ou fermeture de compte, adressez-vous à un administrateur.') }}
            </p>
        </div>
    </div>
</x-app-layout>
