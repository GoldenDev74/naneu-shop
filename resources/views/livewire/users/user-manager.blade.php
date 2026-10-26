<div class="space-y-4">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <x-illustration name="utilisateurs" />
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Utilisateurs et accès') }}</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ __(':n compte(s). Les mots de passe sont hachés (bcrypt, :tours tours) et jamais consultables.', ['n' => $utilisateurs->total(), 'tours' => config('hashing.bcrypt.rounds', 12)]) }}
                </p>
            </div>
        </div>

        @can('create', App\Models\User::class)
            <button wire:click="nouveau"
                    class="btn-primaire">
                <x-icon name="plus" class="h-4 w-4" /> {{ __('Nouveau compte') }}
            </button>
        @endcan
    </div>

    {{-- ============ MOT DE PASSE AFFICHÉ UNE SEULE FOIS ============ --}}
    @if ($motDePasseAffiche)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
            <div class="flex items-start gap-3">
                <x-icon name="warning" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />

                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold text-amber-900 dark:text-amber-200">
                        {{ __('Mot de passe provisoire — affiché une seule fois') }}
                    </h2>
                    <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                        {{ __('Compte :') }} <span class="font-medium">{{ $compteAffiche }}</span>
                    </p>

                    <div class="mt-2 flex flex-wrap items-center gap-2" x-data="{ copie: false }">
                        <code class="rounded bg-white px-3 py-2 font-mono text-base tracking-wider text-slate-900 dark:bg-slate-800 dark:text-white">{{ $motDePasseAffiche }}</code>

                        <button type="button"
                                @click="navigator.clipboard.writeText('{{ $motDePasseAffiche }}'); copie = true; setTimeout(() => copie = false, 2000)"
                                class="rounded-lg border border-amber-300 px-2.5 py-1.5 text-xs font-medium text-amber-800 hover:bg-amber-100 dark:border-amber-700 dark:text-amber-200 dark:hover:bg-amber-900/40">
                            <span x-show="! copie">{{ __('Copier') }}</span>
                            <span x-show="copie" x-cloak>{{ __('Copié') }}</span>
                        </button>
                    </div>

                    <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">
                        {{ $mdpEnvoye ? __('Il a aussi été envoyé par courriel au titulaire.') : __('Aucun courriel envoyé : remettez-le au titulaire de la main à la main.') }}
                        {{ $mdpAChanger ? __('Il devra être changé à la prochaine connexion.') : '' }}
                        {{ __('Fermez ce bandeau une fois le mot de passe transmis.') }}
                    </p>
                </div>

                <button wire:click="masquerMotDePasse"
                        class="shrink-0 rounded p-1 text-amber-600 hover:bg-amber-100 dark:hover:bg-amber-900/40">
                    <x-icon name="x" class="h-4 w-4" />
                </button>
            </div>
        </div>
    @endif

    {{-- ============ FORMULAIRE ============ --}}
    @if ($panneau)
        <form wire:submit="enregistrer"
              class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-doux dark:border-slate-800 dark:bg-slate-900">

            <div class="-mx-5 -mt-5 mb-5 flex items-center gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-marque-50 text-marque-700 ring-1 ring-inset ring-marque-100 dark:bg-marque-900/40 dark:text-marque-300 dark:ring-marque-800/60">
                    <x-icon name="user-round" class="h-5 w-5" />
                </span>
                <h2 class="font-display text-base font-bold text-slate-900 dark:text-white">
                    {{ $enEdition ? __('Modifier le compte') : __('Nouveau compte') }}
                </h2>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field :label="__('Nom complet')" name="name" required>
                    <input type="text" wire:model="name"
                           class="champ w-full">
                </x-form.field>

                <x-form.field :label="__('Nom d\'utilisateur')" name="username"
                              :hint="__('Identifiant de connexion : minuscules, chiffres, point ou tiret. Vide : proposé d’après l’e-mail ou le nom.')">
                    <input type="text" wire:model.blur="username" autocapitalize="none" spellcheck="false" placeholder="prenom.nom"
                           class="champ w-full">
                </x-form.field>

                <x-form.field :label="__('Adresse e-mail')" name="email"
                              :hint="__('Facultative. Permet aussi de se connecter et de recevoir les liens de réinitialisation.')">
                    <input type="email" wire:model="email"
                           class="champ w-full">
                </x-form.field>

                <x-form.field :label="__('Téléphone')" name="phone">
                    <input type="tel" wire:model="phone" placeholder="+228 90 12 34 56"
                           class="champ w-full">
                </x-form.field>

                <x-form.field :label="__('Profil')" name="role" required class="sm:col-span-2">
                    @php
                        // Le profil actuel reste affiché même s'il n'est pas attribuable.
                        $choix = collect($attribuables)
                            ->when(App\Enums\UserRole::tryFrom($role), fn ($c, $r) => $c->push($r))
                            ->unique(fn ($r) => $r->value);
                    @endphp
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($choix as $r)
                            <label wire:key="profil-{{ $r->value }}"
                                   class="flex cursor-pointer gap-3 rounded-xl border p-3 transition {{ $role === $r->value ? 'border-marque-500 bg-marque-50 ring-1 ring-marque-500 dark:bg-marque-900/30' : 'border-slate-200 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800' }}">
                                <input type="radio" wire:model.live="role" value="{{ $r->value }}" class="sr-only">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $r->badgeClasses() }}">
                                    <x-icon :name="match ($r) { App\Enums\UserRole::Caissier => 'shopping-basket', App\Enums\UserRole::Magasinier => 'package', App\Enums\UserRole::Gerant => 'store', default => 'shield' }" class="h-5 w-5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ $r->label() }}</span>
                                    <span class="block text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $r->description() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </x-form.field>
            </div>

            {{-- ---- Menus et droits ---- --}}
            <div class="mt-4 border-t border-marque-200 pt-4 dark:border-marque-900">
                <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white">{{ __('Menus et droits') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Complet : tout ce que le menu permet (créer, modifier, supprimer). Lecture seule : consultation, boutons masqués.') }}
                        </p>
                    </div>
                    @unless ($gerance)
                        <button type="button" wire:click="droitsDuProfil"
                                class="text-xs font-semibold text-marque-600 hover:underline dark:text-marque-400">
                            {{ __('Revenir aux droits du profil') }}
                        </button>
                    @endunless
                </div>

                @if ($gerance)
                    <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                        {{ __('La gérance a accès complet à tous les menus, y compris rapports, comptabilité, utilisateurs, paramètres et journal.') }}
                    </p>
                @else
                    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-entete">
                                <tr>
                                    <x-table.th>{{ __('Menu') }}</x-table.th>
                                    <x-table.th align="center">{{ __('Aucun') }}</x-table.th>
                                    <x-table.th align="center">{{ __('Lecture seule') }}</x-table.th>
                                    <x-table.th align="center">{{ __('Complet') }}</x-table.th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach (App\Enums\UserRole::SECTIONS_ATTRIBUABLES as $section => $libelle)
                                    <tr wire:key="droit-{{ $section }}" class="{{ ($droits[$section] ?? '') === '' ? 'text-slate-400' : 'text-slate-800 dark:text-slate-100' }}">
                                        <td class="px-4 py-2">{{ __($libelle) }}</td>
                                        @foreach (['', 'lecture', 'complet'] as $niveau)
                                            <td class="px-4 py-2 text-center">
                                                @if ($niveau === 'complet' && in_array($section, App\Enums\UserRole::SECTIONS_CONSULTATION, true))
                                                    <span class="text-slate-300 dark:text-slate-600" title="{{ __('Menu de consultation uniquement') }}">—</span>
                                                @else
                                                    <input type="radio" wire:model.live="droits.{{ $section }}" value="{{ $niveau }}"
                                                           aria-label="{{ __($libelle) }} : {{ $niveau === '' ? __('Aucun') : ($niveau === 'lecture' ? __('Lecture seule') : __('Complet')) }}"
                                                           class="h-4 w-4 accent-marque-600">
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Rapports, comptabilité, utilisateurs, paramètres et journal restent réservés à la gérance. Prix de vente, annulations et validation d\'inventaire aussi.') }}
                    </p>
                    @error('droits')
                        <p class="mt-1 text-xs font-medium text-rose-600">{{ __($message) }}</p>
                    @enderror
                @endif
            </div>

            {{-- ---- Mot de passe ---- --}}
            <div class="mt-4 space-y-3 border-t border-marque-200 pt-4 dark:border-marque-900">
                @if ($enEdition)
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        {{ __('Laissez les champs vides pour conserver le mot de passe actuel.') }}
                    </p>
                @endif

                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox" wire:model.live="genererMotDePasse"
                           class="case">
                    {{ __('Générer automatiquement un mot de passe fort') }}
                </label>

                @unless ($genererMotDePasse)
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.field :label="__('Mot de passe')" name="password"
                                      :hint="__('10 caractères minimum, majuscules, minuscules et chiffres. Les mots de passe apparus dans des fuites connues sont refusés.')">
                            <input type="password" wire:model="password" autocomplete="new-password"
                                   class="champ w-full">
                        </x-form.field>

                        <x-form.field :label="__('Confirmer le mot de passe')" name="password_confirmation">
                            <input type="password" wire:model="password_confirmation" autocomplete="new-password"
                                   class="champ w-full">
                        </x-form.field>
                    </div>
                @endunless

                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox" wire:model="must_change_password"
                           class="case">
                    {{ __('Exiger un changement de mot de passe à la première connexion') }}
                </label>

                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox" wire:model="is_active"
                           class="case">
                    {{ __('Compte actif') }}
                </label>

                @error('is_active')
                    <p class="text-xs font-medium text-rose-600">{{ __($message) }}</p>
                @enderror
            </div>

            <div class="mt-4 flex gap-2">
                <button type="submit"
                        class="btn-primaire">
                    {{ $enEdition ? __('Mettre à jour') : __('Créer le compte') }}
                </button>
                <button type="button" wire:click="$set('panneau', false)"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white dark:border-slate-700 dark:text-slate-200">
                    {{ __('Fermer') }}
                </button>
            </div>
        </form>
    @endif

    {{-- ============ FILTRES ============ --}}
    <div class="rounded-2xl border border-slate-200/70 bg-white shadow-doux p-3 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap gap-2">
            <input type="search" wire:model.live.debounce.300ms="recherche" placeholder="{{ __('Nom ou e-mail…') }}"
                   class="champ min-w-[14rem] flex-1">

            <select wire:model.live="filtreRole"
                    class="champ">
                <option value="">{{ __('Tous les rôles') }}</option>
                @foreach ($roles as $r)
                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- ============ LISTE ============ --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-entete">
                    <tr>
                        <x-table.th filter="utilisateur">{{ __('Utilisateur') }}</x-table.th>
                        <x-table.th filter="role" filter-type="select" :options="App\Enums\UserRole::options()">{{ __('Rôle') }}</x-table.th>
                        <x-table.th filter="connexion" filter-type="date">{{ __('Dernière connexion') }}</x-table.th>
                        <x-table.th filter="etat" filter-type="select" :options="['actif' => __('actif'), 'verrouille' => __('verrouillé'), 'suspendu' => __('suspendu')]">{{ __('État') }}</x-table.th>
                        <x-table.th align="right">{{ __('Actions') }}</x-table.th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($utilisateurs as $utilisateur)
                        <tr wire:key="user-{{ $utilisateur->id }}"
                            class="hover:bg-slate-50 dark:hover:bg-slate-800/40 {{ $utilisateur->is_active ? '' : 'opacity-60' }}">

                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200">
                                        {{ $utilisateur->initials() }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="flex items-center gap-1.5 font-medium text-slate-900 dark:text-white">
                                            {{ $utilisateur->name }}
                                            @if ($utilisateur->is(auth()->user()))
                                                <span class="rounded bg-slate-100 px-1.5 text-[10px] text-slate-500 dark:bg-slate-700 dark:text-slate-300">{{ __('vous') }}</span>
                                            @endif
                                        </p>
                                        <p class="truncate text-xs text-slate-400">{{ $utilisateur->username }}@if ($utilisateur->email) · {{ $utilisateur->email }}@endif</p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-xs font-medium {{ $utilisateur->role->badgeClasses() }}">
                                    {{ $utilisateur->role->label() }}
                                </span>
                                @if ($utilisateur->customPermissions() !== null && ! $utilisateur->role->isManagement())
                                    <p class="mt-1 text-[11px] text-amber-600 dark:text-amber-400">{{ __('droits personnalisés') }}</p>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                                @if ($utilisateur->last_login_at)
                                    {{ $utilisateur->last_login_at->diffForHumans() }}
                                    <p class="text-xs text-slate-400">{{ $utilisateur->last_login_ip }}</p>
                                @else
                                    <span class="text-xs">{{ __('jamais connecté') }}</span>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                @if ($utilisateur->is_active && $utilisateur->isLocked())
                                    <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                                          title="{{ __('Trop d\'échecs de connexion. Déverrouillage automatique à :heure.', ['heure' => $utilisateur->locked_until->format('H:i')]) }}">
                                        {{ __('verrouillé') }}
                                    </span>
                                    @can('update', $utilisateur)
                                        <button type="button" wire:click="deverrouiller({{ $utilisateur->id }})"
                                                class="ml-1 text-xs font-semibold text-marque-600 hover:underline dark:text-marque-400">{{ __('Déverrouiller') }}</button>
                                    @endcan
                                @elseif ($utilisateur->is_active)
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                        {{ __('actif') }}
                                    </span>
                                @else
                                    <span class="rounded bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">
                                        {{ __('suspendu') }}
                                    </span>
                                @endif

                                @if ($utilisateur->must_change_password)
                                    <p class="mt-0.5 text-[10px] text-amber-600 dark:text-amber-400">
                                        {{ __('doit changer son mot de passe') }}
                                    </p>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    @can('update', $utilisateur)
                                        <button wire:click="editer({{ $utilisateur->id }})" title="{{ __('Modifier') }}"
                                                class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700">
                                            <x-icon name="pencil" class="h-4 w-4" />
                                        </button>
                                    @endcan

                                    @can('resetPassword', $utilisateur)
                                        <button wire:click="ouvrirMotDePasse({{ $utilisateur->id }})"
                                                title="{{ __('Changer le mot de passe') }}"
                                                class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700">
                                            <x-icon name="key-round" class="h-4 w-4" />
                                        </button>
                                    @endcan

                                    @can('toggleActive', $utilisateur)
                                        <button wire:click="basculerActif({{ $utilisateur->id }})"
                                                title="{{ $utilisateur->is_active ? __('Suspendre') : __('Réactiver') }}"
                                                class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700">
                                            <x-icon :name="$utilisateur->is_active ? 'x' : 'check'" class="h-4 w-4" />
                                        </button>
                                    @endcan

                                    @can('delete', $utilisateur)
                                        <button wire:click="supprimer({{ $utilisateur->id }})"
                                                wire:confirm="Supprimer définitivement le compte de {{ $utilisateur->name }} ?"
                                                title="{{ __('Supprimer') }}"
                                                class="rounded p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-900/30">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($utilisateurs->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $utilisateurs->links() }}</div>
        @endif
    </div>

    {{-- ============ QUI VOIT QUOI ============ --}}
    <details class="group overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-5 py-4">
            <span>
                <span class="block font-semibold text-slate-900 dark:text-white">{{ __('Qui voit quoi') }}</span>
                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Droits proposés par défaut à chaque profil, ajustables compte par compte. Les modifications faites par les employés sont notifiées à la gérance.') }}</span>
            </span>
            <x-icon name="chevron-down" class="h-4 w-4 text-slate-400 transition group-open:rotate-180" />
        </summary>
        <div class="overflow-x-auto border-t border-slate-100 dark:border-slate-800">
            <table class="min-w-full text-sm">
                <thead class="bg-entete">
                    <tr>
                        <x-table.th>{{ __('Menu') }}</x-table.th>
                        @foreach (array_reverse($roles) as $r)
                            <x-table.th align="center">{{ $r->label() }}</x-table.th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($menus as $section => $libelle)
                        <tr>
                            <td class="px-4 py-2 text-slate-700 dark:text-slate-200">{{ $libelle }}</td>
                            @foreach (array_reverse($roles) as $r)
                                <td class="px-4 py-2 text-center">
                                    @php
                                        $niveau = $r->isManagement()
                                            ? App\Enums\AccessLevel::Complet
                                            : ($r->defaultPermissions()[$section] ?? null);
                                        if ($niveau && in_array($section, App\Enums\UserRole::SECTIONS_CONSULTATION, true)) {
                                            $niveau = App\Enums\AccessLevel::Lecture;
                                        }
                                    @endphp
                                    @if (! $niveau)
                                        <span class="text-slate-300 dark:text-slate-600">—</span>
                                    @elseif ($niveau === App\Enums\AccessLevel::Lecture)
                                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('lecture') }}</span>
                                    @else
                                        <x-icon name="check" class="mx-auto h-4 w-4 text-marque-600 dark:text-marque-400" />
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>

    {{-- ============ FENÊTRE : MOT DE PASSE ============ --}}
    @if ($cible)
        <x-popup close="fermerMotDePasse" :titre="__('Mot de passe')" largeur="max-w-lg">
            <x-slot:entete>
                <h2 class="font-display text-lg font-bold text-slate-900 dark:text-white">{{ __('Mot de passe de :nom', ['nom' => $cible->name]) }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('L’ancien mot de passe ne fonctionnera plus.') }}</p>
            </x-slot:entete>

            <form id="form-mdp" wire:submit="appliquerMotDePasse" class="space-y-4">
                <div class="space-y-2">
                    @foreach ([
                        'generer' => [__('Générer un mot de passe provisoire'), __('Affiché une seule fois ici, à lui remettre de la main à la main.'), 'sparkles'],
                        'saisir'  => [__('Choisir le mot de passe'), __('Vous le saisissez et le lui communiquez.'), 'pencil'],
                        'lien'    => [__('Envoyer un lien par e-mail'), __('Il choisit lui-même son mot de passe (courriel requis).'), 'mail'],
                    ] as $mode => [$titre, $aide, $icone])
                        <label wire:key="mode-{{ $mode }}"
                               class="flex cursor-pointer gap-3 rounded-xl border p-3 transition {{ $mdpMode === $mode ? 'border-marque-500 bg-marque-50 ring-1 ring-marque-500 dark:bg-marque-900/30' : 'border-slate-200 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800' }}">
                            <input type="radio" wire:model.live="mdpMode" value="{{ $mode }}" class="sr-only">
                            <x-icon :name="$icone" class="mt-0.5 h-5 w-5 shrink-0 text-marque-600 dark:text-marque-400" />
                            <span>
                                <span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ $titre }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $aide }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                @if ($mdpMode === 'saisir')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.field :label="__('Nouveau mot de passe')" name="nouveauMdp"
                                      :hint="__('10 caractères minimum, majuscules, minuscules et chiffres.')">
                            <input type="password" wire:model="nouveauMdp" id="nouveauMdp" autocomplete="new-password" class="champ w-full">
                        </x-form.field>
                        <x-form.field :label="__('Confirmer')" name="nouveauMdp_confirmation">
                            <input type="password" wire:model="nouveauMdp_confirmation" id="nouveauMdp_confirmation" autocomplete="new-password" class="champ w-full">
                        </x-form.field>
                    </div>
                @endif

                @if ($mdpMode !== 'lien')
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input type="checkbox" wire:model="mdpExigerChangement" class="case">
                        {{ __('Lui demander de le changer à sa prochaine connexion') }}
                    </label>
                @endif
            </form>

            <x-slot:pied>
                <button type="button" wire:click="fermerMotDePasse" class="btn-secondaire">{{ __('Annuler') }}</button>
                <button type="submit" form="form-mdp" class="btn-primaire">
                    {{ $mdpMode === 'lien' ? __('Envoyer le lien') : __('Changer le mot de passe') }}
                </button>
            </x-slot:pied>
        </x-popup>
    @endif
</div>
