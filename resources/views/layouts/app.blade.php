<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="utilisateur-id" content="{{ auth()->id() }}">

    <title>{{ isset($title) ? __($title) : config('naneu.shop.name') }}</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#23804f">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=nunito:600,700,800|nunito-sans:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950">

<div x-data="{ sidebarOpen: false }" class="min-h-full">

    {{-- ============ BARRE LATÉRALE ============ --}}
    <div x-show="sidebarOpen"
         x-transition.opacity
         @click="sidebarOpen = false"
         class="fixed inset-0 z-40 bg-slate-900/60 lg:hidden"
         style="display: none"></div>

    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0 dark:border-slate-800 dark:bg-slate-900">

        <div class="flex h-16 items-center gap-3 border-b border-slate-200 px-5 dark:border-slate-800">
            <x-application-logo class="h-8 w-8 shrink-0 text-marque-500 dark:text-marque-400" aria-hidden="true" />
            <div class="min-w-0">
                <p class="truncate font-display text-[15px] font-extrabold uppercase tracking-[0.12em] text-slate-900 dark:text-white">
                    {{ config('naneu.shop.name') }}
                </p>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ __('Gestion de stock') }}</p>
            </div>
        </div>

        {{-- Carte boutique --}}
        <div class="px-3 pt-4">
            <div class="flex items-center gap-3 rounded-xl border border-slate-200/80 bg-slate-50 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-800/40">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-marque-100 font-display text-xs font-extrabold text-marque-800 dark:bg-marque-900/60 dark:text-marque-200">
                    NS
                </span>
                <div class="min-w-0">
                    <p class="truncate text-[13px] font-semibold text-slate-800 dark:text-slate-100">{{ str(config('naneu.shop.tagline'))->before(' — ') }}</p>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ config('naneu.shop.address') }}</p>
                </div>
            </div>
        </div>

        @php
            // Menus visibles selon le profil : même règle que le middleware
            // `section` des routes (UserRole::canAccess).
            $moi = auth()->user();
            $menus = [
                null => [
                    ['tableau-de-bord', 'tableau-de-bord', 'tableau-de-bord', 'home', __('Tableau de bord')],
                    ['caisse', 'caisse', 'caisse*', 'shopping-basket', __('Caisse')],
                    ['alertes', 'alertes.index', 'alertes.*', 'bell', __('Alertes')],
                ],
                __('Catalogue') => [
                    ['produits', 'produits.index', 'produits.*', 'cube', __('Produits')],
                    ['categories', 'categories.index', 'categories.*', 'tag', __('Catégories')],
                    ['fournisseurs', 'fournisseurs.index', 'fournisseurs.*', 'truck', __('Fournisseurs')],
                ],
                __('Stock') => [
                    ['entrees', 'entrees.index', 'entrees.*', 'arrow-down', __('Entrées')],
                    ['sorties', 'sorties.index', 'sorties.*', 'arrow-up', __('Sorties')],
                    ['lots', 'lots.index', 'lots.*', 'layers', __('Lots')],
                    ['peremptions', 'peremptions.index', 'peremptions.*', 'clock', __('Péremptions')],
                    ['mouvements', 'mouvements.index', 'mouvements.*', 'list', __('Mouvements')],
                    ['inventaires', 'inventaires.index', 'inventaires.*', 'clipboard', __('Inventaires')],
                ],
                __('Pilotage') => [
                    ['rapports', 'rapports.index', 'rapports.*', 'chart', __('Rapports')],
                    ['comptabilite', 'comptabilite.index', 'comptabilite.*', 'wallet', __('Comptabilité')],
                    ['utilisateurs', 'utilisateurs.index', 'utilisateurs.*', 'users', __('Utilisateurs et accès')],
                    ['parametres', 'parametres.index', 'parametres.*', 'cog', __('Paramètres')],
                    ['journal', 'journal.index', 'journal.*', 'shield', __('Journal de sécurité')],
                ],
            ];
        @endphp

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
            @foreach ($menus as $titre => $items)
                @php $visibles = array_filter($items, fn ($i) => $moi->canAccess($i[0])); @endphp
                @if ($visibles)
                    <x-nav.section :title="$titre ?: null">
                        @foreach ($visibles as [$section, $route, $motif, $icone, $libelle])
                            <x-nav.item :href="route($route)" :active="request()->routeIs($motif)" :icon="$icone"
                                        {{-- Seulement ce qui attend encore une action : une alerte
                                             « traitée » reste ouverte (page Alertes) tant que le
                                             problème de stock existe, mais ne compte plus ici. --}}
                                        :badge="$section === 'alertes' ? (\App\Models\StockAlert::query()->unacknowledged()->count() ?: null) : null">
                                {{ $libelle }}
                            </x-nav.item>
                        @endforeach
                    </x-nav.section>
                @endif
            @endforeach
        </nav>

        {{-- Carte utilisateur : profil et déconnexion --}}
        <div class="border-t border-slate-200 p-3 dark:border-slate-800">
            <div class="flex items-center gap-2 rounded-xl p-1.5">
                <a href="{{ route('profile') }}" wire:navigate
                   class="flex min-w-0 flex-1 items-center gap-3 rounded-lg p-1 transition hover:bg-slate-100 dark:hover:bg-slate-800">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-marque-600 text-xs font-bold text-white">
                        {{ auth()->user()->initials() }}
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ auth()->user()->name }}</span>
                        <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->role->label() }}</span>
                    </span>
                </a>

                <form method="POST" action="{{ route('deconnexion') }}">
                    @csrf
                    <button type="submit" title="{{ __('Se déconnecter') }}" aria-label="{{ __('Se déconnecter') }}"
                            class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-rose-50 hover:text-rose-600 dark:text-slate-400 dark:hover:bg-rose-900/30 dark:hover:text-rose-300">
                        <x-icon name="log-out" class="h-4.5 w-4.5" />
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ============ COLONNE PRINCIPALE ============ --}}
    <div class="lg:pl-64">

        <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 dark:border-slate-800 dark:bg-slate-900/90">
            <button @click="sidebarOpen = true" aria-label="{{ __('Ouvrir le menu') }}"
                    class="rounded-md p-2 text-slate-500 hover:bg-slate-100 lg:hidden dark:hover:bg-slate-800">
                <x-icon name="menu" class="h-5 w-5" />
            </button>

            <div class="min-w-0 flex-1">
                @isset($header)
                    <div class="truncate">{{ $header }}</div>
                @endisset
            </div>

            <livewire:notifications.bell />

            <x-locale-switch />

            <x-theme-toggle />

        </header>

        <main class="px-4 py-6 sm:px-6 lg:px-8">
            {{-- Messages de fin d'action après redirection : affichés ici, sur
                 toutes les pages, et non dans un toast qui disparaît. --}}
            @if (session('succes') || session('erreur'))
                <div class="mb-4 space-y-2">
                    <x-flash />
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</div>

<x-toast-host />

@php $inactivite = (int) config('naneu.security.idle_minutes'); @endphp
@if ($inactivite > 0)
    {{-- Déconnexion après inactivité : le compte à rebours repart à chaque
         geste de l'utilisateur, une minute d'avertissement avant la fin. --}}
    <form method="POST" action="{{ route('deconnexion') }}" id="deconnexion-inactivite"
          x-data="{
              limite: {{ $inactivite * 60 }},
              reste: {{ $inactivite * 60 }},
              init() {
                  const repartir = () => { this.reste = this.limite };
                  ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach(e => window.addEventListener(e, repartir, { passive: true }));
                  setInterval(() => { if (--this.reste <= 0) this.$el.submit() }, 1000);
              },
          }"
          class="pointer-events-none fixed inset-x-0 top-4 z-[70] flex justify-center px-4">
        @csrf
        <input type="hidden" name="raison" value="inactivite">
        <div x-show="reste <= 60" style="display: none" x-transition
             class="pointer-events-auto rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-doux dark:border-amber-800 dark:bg-amber-900/40 dark:text-amber-100">
            {{ __('Aucune activité : déconnexion dans') }} <strong x-text="reste" class="tabular-nums"></strong> s.
            <span class="text-amber-700 dark:text-amber-300">{{ __('Touchez l\'écran pour rester connecté.') }}</span>
        </div>
    </form>
@endif

@livewireScripts
</body>
</html>
