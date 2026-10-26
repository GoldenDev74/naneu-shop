@use('App\Support\Money')

<div class="space-y-5">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <x-illustration name="rapports" />
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Rapports') }}</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Ventes, marge et valorisation du stock au coût réel des lots.') }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button wire:click="exporterStock"
                    class="btn-secondaire">
                <x-icon name="download" class="h-4 w-4" /> {{ __('Stock (CSV)') }}
            </button>

            <button wire:click="exporterLots"
                    class="btn-secondaire">
                <x-icon name="download" class="h-4 w-4" /> {{ __('Lots (CSV)') }}
            </button>

            <button wire:click="archiverSurMinio" wire:loading.attr="disabled"
                    class="btn-primaire">
                <x-icon name="layers" class="h-4 w-4" />
                <span wire:loading.remove wire:target="archiverSurMinio">{{ __('Archiver sur MinIO') }}</span>
                <span wire:loading wire:target="archiverSurMinio">{{ __('Archivage…') }}</span>
            </button>
        </div>
    </div>

    {{-- ============ PÉRIODE ============ --}}
    <div class="rounded-2xl border border-slate-200/70 bg-white shadow-doux p-3 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex gap-1">
                @foreach (['jour' => __("Aujourd'hui"), 'semaine' => __('Cette semaine'), 'mois' => __('Ce mois'), 'mois_dernier' => __('Mois dernier'), 'annee' => __('Cette année')] as $cle => $libelle)
                    <button wire:click="periode('{{ $cle }}')"
                            class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        {{ $libelle }}
                    </button>
                @endforeach
            </div>

            <div class="ml-auto flex items-center gap-2">
                <input type="date" wire:model.live="depuis"
                       class="champ-sm">
                <span class="text-slate-400">→</span>
                <input type="date" wire:model.live="jusqua"
                       class="champ-sm">
            </div>
        </div>
    </div>

    {{-- ============ INDICATEURS ============ --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @php $ventesHt = $ventes - $tva; @endphp
        <x-stat-card :label="__('Chiffre d\'affaires TTC')" :value="Money::format($ventes)" icon="chart" tone="positif"
                     :hint="__('HT :ht · TVA collectée :tva', ['ht' => Money::format($ventesHt), 'tva' => Money::format($tva)])" />
        <x-stat-card :label="__('Marge brute')" :value="Money::format($marge)"
                     :hint="$ventesHt > 0 ? __(':taux % du CA HT', ['taux' => round($marge / $ventesHt * 100, 1)]) : null"
                     :tone="$marge >= 0 ? 'positif' : 'danger'" />
        <x-stat-card :label="__('Pertes et démarque')" :value="Money::format($pertes)" icon="warning"
                     :tone="$pertes > 0 ? 'attention' : 'neutre'" />
        <x-stat-card :label="__('Valeur du stock')" :value="Money::format($valeurStock)" icon="cube"
                     :hint="__('au coût d\'achat réel des lots')" />
    </div>

    {{-- ============ COURBE ============ --}}
    <div class="rounded-2xl border border-slate-200/70 bg-white shadow-doux p-5 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Ventes et marge — 30 derniers jours') }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Survolez une barre pour voir les produits vendus, cliquez pour le détail du jour.') }}</p>
        </div>

        @php $max = max(1, $serie->max('ventes')); $nb = $serie->count(); @endphp

        {{-- Pas d'items-end ici : les colonnes doivent s'étirer sur toute la
             hauteur pour que les hauteurs en % des barres soient calculables. --}}
        <div class="flex h-56 gap-1">
            @foreach ($serie as $point)
                @php
                    $choisi = $jourChoisi === $point['date'];
                    // L'infobulle s'ancre du côté opposé au bord le plus proche.
                    $ancre = $loop->index < $nb / 3 ? 'left-0' : ($loop->index > $nb * 2 / 3 ? 'right-0' : 'left-1/2 -translate-x-1/2');
                @endphp
                <button type="button" wire:key="jour-{{ $point['date'] }}" wire:click="choisirJour('{{ $point['date'] }}')"
                        class="group relative flex flex-1 flex-col items-center gap-1 focus-visible:outline-none"
                        aria-pressed="{{ $choisi ? 'true' : 'false' }}"
                        aria-label="{{ $point['jour'] }} : {{ Money::format($point['ventes']) }}">
                    <div class="relative flex w-full flex-1 items-end">
                        <div @class(['w-full rounded-t transition',
                                     'bg-marque-500' => $choisi,
                                     'bg-marque-500/60 group-hover:bg-marque-500 group-focus-visible:bg-marque-500' => ! $choisi])
                             style="height: {{ max(2, round($point['ventes'] / $max * 100)) }}%"></div>

                        <div class="absolute bottom-0 w-full rounded-t bg-marque-800/70"
                             style="height: {{ max(0, round(max(0, $point['marge']) / $max * 100)) }}%"></div>

                        <div class="pointer-events-none absolute bottom-full z-20 mb-2 hidden w-64 rounded-xl bg-slate-900 p-3 text-left text-xs text-white shadow-xl group-hover:block group-focus-visible:block {{ $ancre }}">
                            <p class="font-semibold first-letter:uppercase">{{ $point['jour'] }}</p>
                            <p class="mt-0.5 text-slate-300">
                                {{ Money::format($point['ventes']) }} · {{ __('marge') }} {{ Money::format($point['marge']) }}
                            </p>
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
                            @else
                                <p class="mt-1 text-slate-400">{{ __('Aucune vente ce jour-là.') }}</p>
                            @endif
                        </div>
                    </div>

                    <span @class(['text-[9px] tabular-nums',
                                  'font-bold text-marque-700 dark:text-marque-300' => $choisi,
                                  'text-slate-400' => ! $choisi && $loop->index % 3 === 0,
                                  'text-transparent' => ! $choisi && $loop->index % 3 !== 0])>{{ $point['libelle'] }}</span>
                </button>
            @endforeach
        </div>

        <div class="mt-3 flex gap-4 text-xs text-slate-500 dark:text-slate-400">
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-sm bg-marque-500/70"></span> {{ __('Chiffre d\'affaires TTC') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-sm bg-marque-800/70"></span> {{ __('Marge') }}
            </span>
        </div>
    </div>

    {{-- ============ PRODUITS VENDUS (graphique) ============ --}}
    <div class="rounded-2xl border border-slate-200/70 bg-white shadow-doux p-5 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Produits vendus') }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    @if ($jourChoisi)
                        <span class="first-letter:uppercase">{{ \Illuminate\Support\Carbon::parse($jourChoisi)->translatedFormat('l j F Y') }}</span>
                    @else
                        {{ __('Du :du au :au', ['du' => \Illuminate\Support\Carbon::parse($depuis)->format('d/m/Y'), 'au' => \Illuminate\Support\Carbon::parse($jusqua)->format('d/m/Y')]) }}
                    @endif
                    · {{ __('15 premiers, classés par chiffre d\'affaires') }}
                </p>
            </div>
            @if ($jourChoisi)
                <button type="button" wire:click="choisirJour(null)" class="btn-secondaire px-3 py-1.5 text-xs">
                    <x-icon name="x" class="h-3.5 w-3.5" /> {{ __('Revenir à la période') }}
                </button>
            @endif
        </div>

        @php $maxProduit = max(1, collect($produitsVendus)->max('chiffre')); @endphp

        <ul class="space-y-2.5">
            @forelse ($produitsVendus as $produit)
                <li wire:key="pv-{{ $produit['id'] }}" class="grid grid-cols-[minmax(0,9rem)_1fr_auto] items-center gap-3 text-sm sm:grid-cols-[minmax(0,14rem)_1fr_auto]">
                    <span class="truncate font-medium text-slate-800 dark:text-slate-100" title="{{ $produit['nom'] }}">{{ $produit['nom'] }}</span>
                    <div class="relative h-5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                         title="{{ __('CA :ca · marge :m', ['ca' => Money::format($produit['chiffre']), 'm' => Money::format($produit['marge'])]) }}">
                        <div class="absolute inset-y-0 left-0 rounded-full bg-marque-400/80" style="width: {{ round($produit['chiffre'] / $maxProduit * 100, 1) }}%"></div>
                        <div class="absolute inset-y-0 left-0 rounded-full bg-marque-700/80" style="width: {{ round(max(0, $produit['marge']) / $maxProduit * 100, 1) }}%"></div>
                    </div>
                    <span class="text-right tabular-nums sm:w-40">
                        <span class="font-semibold text-slate-900 dark:text-white">{{ Money::format($produit['chiffre']) }}</span>
                        <span class="block text-[11px] text-slate-400">× {{ rtrim(rtrim(Money::number($produit['quantite'], 3), '0'), ',.') }} · {{ __('marge') }} {{ Money::format($produit['marge']) }}</span>
                    </span>
                </li>
            @empty
                <li class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('Aucune vente sur cette période.') }}</li>
            @endforelse
        </ul>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">

        {{-- ============ MEILLEURES VENTES ============ --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Meilleures ventes de la période') }}</h2>
            </div>

            <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-800">
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($topProduits as $index => $produit)
                        <tr wire:key="top-{{ $produit['id'] }}">
                            <td class="px-4 py-2.5">
                                <span class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-100 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {{ $index + 1 }}
                                </span>
                                <span class="font-medium text-slate-900 dark:text-white">{{ $produit['nom'] }}</span>
                                <span class="ml-1 text-xs text-slate-400">{{ $produit['sku'] }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-right text-sm tabular-nums text-slate-600 dark:text-slate-300">
                                {{ rtrim(rtrim(\App\Support\Money::number($produit['quantite'], 3), '0'), ',.') }}
                            </td>
                            <td class="px-4 py-2.5 text-right text-sm font-medium tabular-nums text-slate-900 dark:text-white">
                                {{ Money::format($produit['chiffre']) }}
                            </td>
                            <td class="px-4 py-2.5 text-right text-sm tabular-nums {{ $produit['marge'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ Money::format($produit['marge']) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                                {{ __('Aucune vente sur cette période.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ============ RÉAPPROVISIONNEMENT ============ --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Liste de réapprovisionnement') }}</h2>
                <span class="text-sm text-slate-500 dark:text-slate-400">{{ $aReappro->count() }} {{ __('produit(s)') }}</span>
            </div>

            <div class="max-h-96 overflow-y-auto">
                <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-800">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($aReappro as $produit)
                            <tr wire:key="reap-{{ $produit->id }}">
                                <td class="px-4 py-2.5">
                                    <p class="font-medium text-slate-900 dark:text-white">{{ $produit->name }}</p>
                                    <p class="text-xs text-slate-400">
                                        {{ $produit->sku }}
                                        @if ($produit->defaultSupplier) · {{ $produit->defaultSupplier->name }} @endif
                                    </p>
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <span class="rounded px-2 py-0.5 text-sm tabular-nums {{ $produit->stockStatusClasses() }}">
                                        {{ rtrim(rtrim(\App\Support\Money::number((float) $produit->quantity_on_hand, 3), '0'), ',.') }}
                                        {{ $produit->unit?->symbol }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-right text-xs text-slate-500 dark:text-slate-400">
                                    {{ __('commander') }}
                                    <span class="font-medium">
                                        {{ rtrim(rtrim(\App\Support\Money::number((float) ($produit->reorder_quantity ?: $produit->alert_quantity * 2), 3), '0'), ',.') }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                                    {{ __('Aucun produit sous son seuil.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
