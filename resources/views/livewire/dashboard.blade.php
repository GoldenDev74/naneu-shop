@php $moi = auth()->user(); @endphp
@use('App\Support\Money')

@php
    $prenom = str(auth()->user()->name)->before(' ');
    $tendance = ($voitFinances && $chiffres['ventes_hier'] > 0)
        ? (int) round(($chiffres['ventes_jour'] - $chiffres['ventes_hier']) / $chiffres['ventes_hier'] * 100)
        : null;
    // La marge se rapporte au hors-taxe : la TVA n'appartient pas à la boutique.
    $ventesHtJour = $voitFinances ? $chiffres['ventes_jour'] - $chiffres['tva_jour'] : 0;
    $tauxMarge = $ventesHtJour > 0
        ? (int) round($chiffres['marge_jour'] / $ventesHtJour * 100)
        : null;
@endphp

<div class="space-y-6">

    {{-- ============ EN-TÊTE ============ --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-[28px] dark:text-white">
                {{ __(now()->hour < 18 ? 'Bonjour, :nom' : 'Bonsoir, :nom', ['nom' => $prenom]) }}
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ str(now()->translatedFormat('l j F Y'))->ucfirst() }}
                · {{ __('Voici l\'état de la boutique aujourd\'hui.') }}
            </p>
        </div>

        <div class="flex gap-2">
            @if ($moi->canWrite('entrees'))
                <a href="{{ route('entrees.create') }}" wire:navigate class="btn-secondaire">
                    <x-icon name="arrow-down" class="h-4 w-4" /> {{ __('Nouvelle entrée') }}
                </a>
            @endif
            @if ($moi->canWrite('sorties'))
                <a href="{{ route('sorties.create') }}" wire:navigate class="btn-primaire">
                    <x-icon name="plus" class="h-4 w-4" /> {{ __('Nouvelle sortie') }}
                </a>
            @endif
        </div>
    </div>

    {{-- ============ INDICATEURS ============ --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" data-reveal>
        @if ($voitFinances)
            <x-stat-card
                :label="__('Ventes du jour')"
                :value="Money::format($chiffres['ventes_jour'])"
                :count="$chiffres['ventes_jour']" money
                :trend="$tendance"
                :hint="$tendance !== null ? __('vs hier') : __('Hier : :montant', ['montant' => Money::format($chiffres['ventes_hier'])])"
                icon="wallet" tone="positif" />

            <x-stat-card
                :label="__('Marge du jour')"
                :value="Money::format($chiffres['marge_jour'])"
                :count="$chiffres['marge_jour']" money
                :hint="$tauxMarge !== null ? __(':taux % des ventes HT', ['taux' => $tauxMarge]) : __('Aucune vente aujourd\'hui')"
                icon="trending-up" tone="positif" />

            <x-stat-card
                :label="__('Valeur du stock')"
                :value="Money::format($chiffres['valeur_stock'])"
                :count="$chiffres['valeur_stock']" money
                :hint="__(':n produits actifs', ['n' => $chiffres['produits_actifs']])"
                icon="package" />
        @else
            <x-stat-card
                :label="__('Produits actifs')"
                :value="\App\Support\Money::number($chiffres['produits_actifs'], 0)"
                :count="$chiffres['produits_actifs']"
                icon="package" />

            <x-stat-card
                :label="__('Alertes ouvertes')"
                :value="\App\Support\Money::number($chiffres['alertes_ouvertes'], 0)"
                :count="$chiffres['alertes_ouvertes']"
                icon="bell"
                :tone="$chiffres['alertes_critiques'] > 0 ? 'danger' : 'neutre'"
                :badge="$chiffres['alertes_critiques'] > 0 ? __(':n critique(s)', ['n' => $chiffres['alertes_critiques']]) : null"
                :href="$moi->canAccess('alertes') ? route('alertes.index') : null" />

            <x-stat-card
                :label="__('Péremptions')"
                :value="\App\Support\Money::number($chiffres['lots_bientot_perimes'], 0)"
                :count="$chiffres['lots_bientot_perimes']"
                :hint="__('lot(s) bientôt périmé(s)')"
                icon="clock"
                :tone="$chiffres['lots_perimes'] > 0 ? 'danger' : ($chiffres['lots_bientot_perimes'] > 0 ? 'attention' : 'positif')"
                :badge="$chiffres['lots_perimes'] > 0 ? __(':n périmé(s)', ['n' => $chiffres['lots_perimes']]) : null"
                :href="$moi->canAccess('peremptions') ? route('peremptions.index') : null" />
        @endif

        <x-stat-card
            :label="__('Stock bas')"
            :value="__(':n produits', ['n' => Money::format($chiffres['stock_bas'] + $chiffres['ruptures'], false)])"
            :count="$chiffres['stock_bas'] + $chiffres['ruptures']" :suffix="__('produits')"
            icon="warning"
            :tone="$chiffres['ruptures'] > 0 ? 'danger' : ($chiffres['stock_bas'] > 0 ? 'attention' : 'positif')"
            :badge="$chiffres['ruptures'] > 0 ? __(':n en rupture', ['n' => $chiffres['ruptures']]) : null"
            :hint="__('à réapprovisionner')"
            :href="$moi->canAccess('produits') ? route('produits.index', ['statut' => 'reappro']) : null" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- ============ GRAPHIQUE ============ --}}
        @if ($voitFinances)
            @php
                $max = max(1, $serie->max('ventes'));
                $total = $serie->sum('ventes');
            @endphp

            <section class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-doux lg:col-span-2 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('Ventes des 14 derniers jours') }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ Money::format($total) }} {{ __('au total · moyenne') }} {{ Money::format(intdiv($total, max(1, $serie->count()))) }} {{ __('par jour') }}
                        </p>
                    </div>
                    @if ($moi->canAccess('rapports'))
                        <a href="{{ route('rapports.index') }}" wire:navigate
                           class="inline-flex items-center gap-1 text-sm font-semibold text-marque-600 hover:text-marque-700 dark:text-marque-400">
                            {{ __('Rapports') }} <x-icon name="chevron-right" class="h-4 w-4" />
                        </a>
                    @endif
                </div>

                <div class="relative mt-6 flex h-52 gap-2">
                    {{-- Lignes de repère --}}
                    <div class="pointer-events-none absolute inset-x-0 top-0 bottom-6 flex flex-col justify-between" aria-hidden="true">
                        @foreach (range(1, 4) as $i)
                            <div class="border-t border-dashed border-slate-200 dark:border-slate-800"></div>
                        @endforeach
                    </div>

                    @if ($total == 0)
                        <p class="absolute inset-x-0 top-1/3 text-center text-sm text-slate-500 dark:text-slate-400">
                            {{ __('Aucune vente enregistrée sur ces 14 jours.') }}
                        </p>
                    @endif

                    @foreach ($serie as $point)
                        @php $aujourdhui = $loop->last; @endphp
                        <div class="group relative flex flex-1 flex-col items-center gap-2">
                            <div class="relative flex w-full flex-1 items-end justify-center">
                                <div data-bar
                                     @class([
                                         'w-full max-w-9 origin-bottom rounded-t-md transition-colors',
                                         'bg-marque-600 dark:bg-marque-400' => $aujourdhui,
                                         'bg-marque-200 group-hover:bg-marque-300 dark:bg-marque-900 dark:group-hover:bg-marque-800' => ! $aujourdhui,
                                     ])
                                     style="height: {{ max(2, round($point['ventes'] / $max * 100)) }}%"></div>

                                <div @class(['pointer-events-none absolute bottom-full z-20 mb-2 hidden w-60 rounded-xl bg-slate-900 p-3 text-left text-xs text-white shadow-xl group-hover:block',
                                             'left-0' => $loop->index < 5,
                                             'right-0' => $loop->index > 8,
                                             'left-1/2 -translate-x-1/2' => $loop->index >= 5 && $loop->index <= 8])>
                                    <p class="font-semibold first-letter:uppercase">{{ $point['jour'] }}</p>
                                    <p class="mt-0.5 text-slate-300">{{ Money::format($point['ventes']) }} · {{ __('marge') }} {{ Money::format($point['marge']) }}</p>
                                    @if ($point['produits'])
                                        <ul class="mt-2 space-y-1 border-t border-white/10 pt-2">
                                            @foreach ($point['produits'] as $p)
                                                <li class="flex justify-between gap-3">
                                                    <span class="truncate">{{ $p['nom'] }} <span class="text-slate-400">× {{ $p['quantite'] }}</span></span>
                                                    <span class="shrink-0 tabular-nums text-slate-300">{{ Money::format($p['chiffre']) }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                        @if ($point['nb_produits'] > count($point['produits']))
                                            <p class="mt-1 text-slate-400">{{ __('+ :n autre(s) produit(s)', ['n' => $point['nb_produits'] - count($point['produits'])]) }}</p>
                                        @endif
                                    @endif
                                </div>
                            </div>
                            <span @class([
                                'text-[10px] tabular-nums',
                                'font-bold text-slate-900 dark:text-white' => $aujourdhui,
                                'text-slate-500 dark:text-slate-400' => ! $aujourdhui,
                            ])>{{ $aujourdhui ? __('Auj.') : $point['libelle'] }}</span>
                        </div>
                    @endforeach
                </div>

                {{-- Chiffres du mois --}}
                <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-slate-100 pt-5 sm:grid-cols-4 dark:border-slate-800">
                    @foreach ([
                        [__('Ventes du mois'), $chiffres['ventes_mois'], 'bg-marque-600'],
                        [__('Marge du mois'), $chiffres['marge_mois'], 'bg-marque-400'],
                        [__('Achats du mois'), $chiffres['entrees_mois'], 'bg-slate-400'],
                        [__('Pertes du mois'), $chiffres['pertes_mois'], $chiffres['pertes_mois'] > 0 ? 'bg-amber-400' : 'bg-slate-300'],
                    ] as [$libelle, $montant, $couleur])
                        <div>
                            <dt class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                <span class="h-2 w-2 rounded-full {{ $couleur }}"></span> {{ $libelle }}
                            </dt>
                            <dd class="mt-1 font-display text-base font-bold tabular-nums text-slate-900 dark:text-white">
                                {{ Money::format($montant) }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        {{-- ============ STOCK BAS ============ --}}
        <section @class([
            'flex flex-col rounded-2xl border border-slate-200/70 bg-white p-5 shadow-doux dark:border-slate-800 dark:bg-slate-900',
            'lg:col-span-3' => ! $voitFinances,
        ])>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('Stock bas') }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Produits sous leur seuil d\'alerte') }}</p>
                </div>
                @if ($moi->canAccess('produits'))
                    <a href="{{ route('produits.index', ['statut' => 'reappro']) }}" wire:navigate
                       class="inline-flex items-center gap-1 text-sm font-semibold text-marque-600 hover:text-marque-700 dark:text-marque-400">
                        {{ __('Tout voir') }} <x-icon name="chevron-right" class="h-4 w-4" />
                    </a>
                @endif
            </div>

            <div class="mt-3 flex-1 divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($aReapprovisionner as $produit)
                    @php
                        $qte = (float) $produit->quantity_on_hand;
                        $seuil = max(0.001, (float) $produit->alert_quantity);
                        $rupture = $qte <= 0;
                        $remplissage = min(100, max(4, round($qte / $seuil * 100)));
                    @endphp

                    <div wire:key="reappro-{{ $produit->id }}" class="flex items-center gap-3 py-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            <x-icon name="package" class="h-4 w-4" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $produit->name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ __('Seuil') }} {{ $produit->unit?->formatQuantity($seuil) ?? rtrim(rtrim(\App\Support\Money::number($seuil, 3), '0'), ',.') }}
                            </p>
                            <div class="mt-1.5 h-1 w-24 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div @class(['h-full rounded-full', 'bg-rose-500' => $rupture, 'bg-amber-400' => ! $rupture])
                                     style="width: {{ $rupture ? 100 : $remplissage }}%"></div>
                            </div>
                        </div>

                        @if ($rupture)
                            <span class="shrink-0 rounded-md bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-600 dark:bg-rose-900/30 dark:text-rose-300">
                                {{ __('Rupture') }}
                            </span>
                        @else
                            <span class="shrink-0 text-sm tabular-nums text-slate-500 dark:text-slate-400">
                                <strong class="font-bold text-slate-900 dark:text-white">
                                    {{ $produit->unit?->formatQuantity($qte) ?? rtrim(rtrim(\App\Support\Money::number($qte, 3), '0'), ',.') }}
                                </strong>
                                {{ __('restant') }}
                            </span>
                        @endif
                    </div>
                @empty
                    <div class="flex flex-col items-center py-10 text-center">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-marque-50 text-marque-600 dark:bg-marque-900/40 dark:text-marque-300">
                            <x-icon name="circle-check" class="h-5 w-5" />
                        </span>
                        <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Aucun produit sous son seuil') }}</p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Le stock est suffisant partout.') }}</p>
                    </div>
                @endforelse
            </div>

            @if ($aReapprovisionner->isNotEmpty() && $moi->canWrite('entrees'))
                <a href="{{ route('entrees.create') }}" wire:navigate class="btn-secondaire mt-3 w-full">
                    <x-icon name="arrow-down" class="h-4 w-4" /> {{ __('Enregistrer une entrée') }}
                </a>
            @endif
        </section>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">

        {{-- ============ PÉREMPTIONS IMMINENTES ============ --}}
        <section class="flex flex-col rounded-2xl border border-slate-200/70 bg-white p-5 shadow-doux dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('Péremptions imminentes') }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Lots à écouler en priorité') }}</p>
                </div>
                @if ($moi->canAccess('peremptions'))
                    <a href="{{ route('peremptions.index') }}" wire:navigate
                       class="inline-flex items-center gap-1 text-sm font-semibold text-marque-600 hover:text-marque-700 dark:text-marque-400">
                        {{ __('Gérer') }} <x-icon name="chevron-right" class="h-4 w-4" />
                    </a>
                @endif
            </div>

            <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($lotsCritiques as $lot)
                    @php $jours = $lot->daysUntilExpiry(); @endphp

                    <div wire:key="lot-{{ $lot->id }}" class="flex items-center gap-3 py-3">
                        <span @class([
                            'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                            'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300' => $jours < 0,
                            'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => $jours >= 0,
                        ])>
                            <x-icon name="clock" class="h-4 w-4" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $lot->product?->name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ __('Lot') }} {{ $lot->batch_number }} ·
                                {{ $lot->product?->unit?->formatQuantity((float) $lot->quantity_remaining)
                                   ?? rtrim(rtrim(\App\Support\Money::number((float) $lot->quantity_remaining, 3), '0'), ',.') }}
                            </p>
                        </div>

                        <span @class([
                            'shrink-0 rounded-md px-2 py-1 text-xs font-semibold tabular-nums',
                            'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300' => $jours < 0,
                            'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => $jours >= 0,
                        ])>
                            {{ $jours < 0 ? __('Périmé') : ($jours === 0 ? __("Aujourd'hui") : __('J-:n', ['n' => $jours])) }}
                        </span>
                    </div>
                @empty
                    <div class="flex flex-col items-center py-10 text-center">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-marque-50 text-marque-600 dark:bg-marque-900/40 dark:text-marque-300">
                            <x-icon name="circle-check" class="h-5 w-5" />
                        </span>
                        <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Aucun lot proche de la péremption') }}</p>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- ============ ALERTES ============ --}}
        <section class="flex flex-col rounded-2xl border border-slate-200/70 bg-white p-5 shadow-doux dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('Alertes en cours') }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        {{ $chiffres['alertes_ouvertes'] }} {{ __('ouverte(s), les plus graves en premier') }}
                    </p>
                </div>
                @if ($moi->canAccess('alertes'))
                    <a href="{{ route('alertes.index') }}" wire:navigate
                       class="inline-flex items-center gap-1 text-sm font-semibold text-marque-600 hover:text-marque-700 dark:text-marque-400">
                        {{ __('Tout voir') }} <x-icon name="chevron-right" class="h-4 w-4" />
                    </a>
                @endif
            </div>

            <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($alertes as $alerte)
                    <div wire:key="alerte-{{ $alerte->id }}" class="flex gap-3 py-3">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $alerte->severity->dotClasses() }}"></span>
                        <div class="min-w-0">
                            <p class="text-sm text-slate-800 dark:text-slate-100">{{ $alerte->message }}</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                {{ $alerte->type->label() }} · {{ $alerte->triggered_at?->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center py-10 text-center">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-marque-50 text-marque-600 dark:bg-marque-900/40 dark:text-marque-300">
                            <x-icon name="circle-check" class="h-5 w-5" />
                        </span>
                        <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Aucune alerte') }}</p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Stock et péremptions sous contrôle.') }}</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
