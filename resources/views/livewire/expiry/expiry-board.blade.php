@use('App\Support\Money')

@php
    $qte = fn ($produit, float $q) => $produit?->unit?->formatQuantity($q)
        ?? rtrim(rtrim(Money::number($q, 3), '0'), ',.');
    $rythmeTxt = fn ($produit, float $r) => $r > 0
        ? __('≈ :q / jour', ['q' => rtrim(rtrim(Money::number($r, 1), '0'), ',.').' '.($produit?->unit?->symbol ?? '')])
        : __('aucune vente sur 4 semaines');
@endphp

<div class="space-y-5">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <x-illustration name="peremptions" />
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Péremptions') }}</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Quoi retirer, quoi pousser à la vente, quoi commander moins — d\'après le rythme de vente des 4 dernières semaines.') }}
                </p>
            </div>
        </div>

        @if ($peutSortir && $analyse['perimes']->isNotEmpty())
            <button wire:click="$set('confirmationSortie', true)" class="btn-danger">
                <x-icon name="trash" class="h-4 w-4" />
                {{ __('Retirer tous les périmés') }}
            </button>
        @endif
    </div>

    {{-- ============ CONFIRMATION ============ --}}
    @if ($confirmationSortie)
        <div class="rounded-2xl border border-rose-300 bg-rose-50 p-4 dark:border-rose-800 dark:bg-rose-900/20">
            <h2 class="font-semibold text-rose-900 dark:text-rose-200">{{ __('Retirer du stock tous les lots périmés ?') }}</h2>
            <p class="mt-1 text-sm text-rose-800 dark:text-rose-300">
                {{ $analyse['perimes']->count() }} {{ __('lot(s) seront sortis avec le motif « Péremption ».') }}
                @if ($voitPrix)
                    {{ __('Une perte de') }} <strong>{{ Money::format($valeurPerimee) }}</strong> {{ __('sera enregistrée.') }}
                @endif
                {{ __('L\'opération est tracée au grand livre et reste annulable depuis le bon de sortie.') }}
            </p>
            <div class="mt-3 flex gap-2">
                <button wire:click="sortirPerimes" wire:loading.attr="disabled" class="btn-danger">
                    <span wire:loading.remove wire:target="sortirPerimes">{{ __('Confirmer la sortie') }}</span>
                    <span wire:loading wire:target="sortirPerimes">{{ __('Traitement…') }}</span>
                </button>
                <button wire:click="$set('confirmationSortie', false)" class="btn-secondaire">{{ __('Revenir') }}</button>
            </div>
        </div>
    @endif

    {{-- ============ SYNTHÈSE : une carte = une décision ============ --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat-card :label="__('À retirer du rayon')" :value="$perimesParProduit->count()" icon="trash"
                     :tone="$perimesParProduit->isNotEmpty() ? 'danger' : 'positif'"
                     :hint="$voitPrix && $valeurPerimee ? __('perte :montant', ['montant' => Money::format($valeurPerimee)]) : __('produit(s) périmé(s)')" />

        <x-stat-card :label="__('Ne se vendront pas à temps')" :value="$voitPrix ? Money::format($valeurRisque) : $analyse['a_risque']->count()" icon="warning"
                     :tone="$analyse['a_risque']->isNotEmpty() ? 'attention' : 'positif'"
                     :hint="__(':n lot(s) à pousser à la vente', ['n' => $analyse['a_risque']->count()])" />

        <x-stat-card :label="__('Partiront à temps')" :value="$analyse['a_temps']->count()" icon="check" tone="positif"
                     :hint="__('lot(s) sous :n jours, rythme suffisant', ['n' => $analyse['horizon']])" />

        <x-stat-card :label="__('Frais sans date')" :value="$sansDateParProduit->count()" icon="clock"
                     :tone="$sansDateParProduit->isNotEmpty() ? 'attention' : 'positif'"
                     :hint="__('produit(s) impossibles à suivre')" />
    </div>

    {{-- ============ 1. À RETIRER ============ --}}
    @if ($perimesParProduit->isNotEmpty())
        <section class="overflow-hidden rounded-2xl border border-rose-200 bg-white shadow-doux dark:border-rose-900 dark:bg-slate-900">
            <header class="flex items-center justify-between gap-3 border-b border-rose-100 bg-rose-50 px-4 py-3 dark:border-rose-900 dark:bg-rose-900/20">
                <div>
                    <h2 class="font-semibold text-rose-900 dark:text-rose-200">{{ __('À retirer du rayon maintenant') }}</h2>
                    <p class="text-xs text-rose-700/80 dark:text-rose-300/80">{{ __('La vente de ces produits est bloquée à la caisse.') }}</p>
                </div>
            </header>

            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($perimesParProduit as $productId => $items)
                    @php
                        $produit = $items->first()['lot']->product;
                        $quantite = $items->sum(fn ($i) => (float) $i['lot']->quantity_remaining);
                        $depuis = abs($items->min('jours'));
                    @endphp
                    <li wire:key="perime-{{ $productId }}" class="flex flex-wrap items-center gap-3 px-4 py-3">
                        @include('livewire.stock.partials.vignette', ['produit' => $produit])
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-slate-900 dark:text-white">{{ $produit?->name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $qte($produit, $quantite) }} · {{ trans_choice(':n lot|:n lots', $items->count(), ['n' => $items->count()]) }}
                                · {{ __('périmé depuis :n j', ['n' => $depuis]) }}
                                @if ($items->contains(fn ($i) => $i['lot']->expiry_estimated)) · <span class="italic">{{ __('date estimée') }}</span> @endif
                            </p>
                        </div>
                        @if ($voitPrix)
                            <p class="text-sm font-semibold tabular-nums text-rose-700 dark:text-rose-300">−{{ Money::format($items->sum('valeur')) }}</p>
                        @endif
                        @if ($peutSortir)
                            <button wire:click="sortirProduit({{ $productId }})" wire:loading.attr="disabled" wire:target="sortirProduit({{ $productId }})"
                                    class="rounded-full border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-900/30">
                                {{ __('Retirer') }}
                            </button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- ============ 2. À POUSSER À LA VENTE ============ --}}
    @if ($analyse['a_risque']->isNotEmpty())
        <section class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-doux dark:border-amber-900 dark:bg-slate-900">
            <header class="border-b border-amber-100 bg-amber-50 px-4 py-3 dark:border-amber-900 dark:bg-amber-900/20">
                <h2 class="font-semibold text-amber-900 dark:text-amber-200">{{ __('Ne se vendront pas à temps au rythme actuel') }}</h2>
                <p class="text-xs text-amber-800/80 dark:text-amber-300/80">{{ __('Mettez-les en avant, baissez le prix, ou voyez un retour avec le fournisseur avant la date.') }}</p>
            </header>

            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($analyse['a_risque'] as $a)
                    @php $lot = $a['lot']; $produit = $lot->product; @endphp
                    <li wire:key="risque-{{ $lot->id }}" class="flex flex-wrap items-center gap-3 px-4 py-3">
                        @include('livewire.stock.partials.vignette', ['produit' => $produit])

                        <div class="min-w-0 flex-1">
                            <a @if (auth()->user()->canAccess('lots')) href="{{ route('produits.lots', $lot->product_id) }}" wire:navigate @endif
                               class="font-medium text-slate-900 hover:text-marque-700 dark:text-white dark:hover:text-marque-300">{{ $produit?->name }}</a>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ __('En stock') }} {{ $qte($produit, (float) $lot->quantity_remaining) }}
                                · {{ $rythmeTxt($produit, $a['rythme']) }}
                                · <span class="font-mono">{{ $lot->batch_number }}</span>
                            </p>
                        </div>

                        <div class="text-right">
                            <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                                {{ __('≈ :q invendus', ['q' => $qte($produit, $a['invendu'])]) }}
                                @if ($voitPrix) <span class="font-normal text-slate-500">· {{ Money::format($a['valeur_risque']) }}</span> @endif
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $a['jours'] === 0 ? __('date limite aujourd\'hui') : __('date limite dans :n j', ['n' => $a['jours']]) }}
                                ({{ $lot->expiry_date->format('d/m') }}@if ($lot->expiry_estimated), {{ __('estimée') }}@endif)
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- ============ 3. FRAIS SANS DATE ============ --}}
    @if ($sansDateParProduit->isNotEmpty())
        <section class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
            <header class="border-b border-slate-100 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-800/40">
                <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Produits frais sans date limite') }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Oignons, piment, légumes… n\'ont pas de date imprimée. Indiquez combien de jours ils se gardent : leurs lots seront datés depuis la réception, et la vente sera bloquée une fois passés.') }}
                </p>
            </header>

            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($sansDateParProduit as $productId => $items)
                    @php
                        $produit = $items->first()['lot']->product;
                        $quantite = $items->sum(fn ($i) => (float) $i['lot']->quantity_remaining);
                        $age = $items->max('age');
                    @endphp
                    <li wire:key="sansdate-{{ $productId }}" class="flex flex-wrap items-center gap-3 px-4 py-3">
                        @include('livewire.stock.partials.vignette', ['produit' => $produit])
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-slate-900 dark:text-white">{{ $produit?->name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $qte($produit, $quantite) }} {{ __('en stock') }}
                                · <span @class(['font-semibold text-amber-700 dark:text-amber-300' => $age >= 7])>{{ __('le plus ancien reçu il y a :n j', ['n' => $age]) }}</span>
                                · {{ $rythmeTxt($produit, $items->first()['rythme']) }}
                            </p>
                        </div>

                        @if ($voitPrix)
                            <form wire:submit="definirConservation({{ $productId }})" class="flex items-center gap-2">
                                <label class="sans-tuile flex items-center gap-2 text-xs text-slate-500">
                                    {{ __('Se garde') }}
                                    <input type="number" min="1" max="730" wire:model="conservations.{{ $productId }}" required
                                           class="champ-sm w-16 text-right tabular-nums" aria-label="{{ __('Durée de conservation en jours') }}">
                                    {{ __('jours') }}
                                </label>
                                <button type="submit" class="btn-primaire px-3 py-1.5 text-xs">{{ __('Dater') }}</button>
                            </form>
                            @error("conservations.{$productId}")
                                <p class="w-full text-right text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- ============ 4. CE QUI SE PERD LE PLUS ============ --}}
    @if ($analyse['pertes']->isNotEmpty())
        <section class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
            <header class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Jetés pour péremption — 4 dernières semaines') }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Commandez ces produits en plus petites quantités, ou plus souvent.') }}</p>
            </header>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($analyse['pertes'] as $perte)
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                        <span class="font-medium text-slate-800 dark:text-slate-100">{{ $perte->name }}</span>
                        <span class="text-right text-slate-500 dark:text-slate-400">
                            {{ rtrim(rtrim(Money::number((float) $perte->quantite, 3), '0'), ',.') }} {{ $perte->unite }}
                            · {{ trans_choice(':n fois|:n fois', $perte->fois, ['n' => $perte->fois]) }}
                            @if ($voitPrix) · <span class="font-semibold text-rose-700 dark:text-rose-300">−{{ Money::format((int) $perte->valeur) }}</span> @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- ============ 5. SOUS CONTRÔLE ============ --}}
    @if ($analyse['a_temps']->isNotEmpty())
        <details class="group overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
            <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3">
                <span class="font-semibold text-slate-900 dark:text-white">
                    {{ __('Partiront à temps') }} <span class="font-normal text-slate-500">({{ $analyse['a_temps']->count() }})</span>
                </span>
                <x-icon name="chevron-down" class="h-4 w-4 text-slate-400 transition group-open:rotate-180" />
            </summary>
            <ul class="divide-y divide-slate-100 border-t border-slate-100 dark:divide-slate-800 dark:border-slate-800">
                @foreach ($analyse['a_temps'] as $a)
                    @php $lot = $a['lot']; @endphp
                    <li wire:key="temps-{{ $lot->id }}" class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                        <span class="text-slate-800 dark:text-slate-100">{{ $lot->product?->name }}</span>
                        <span class="text-right text-xs text-slate-500 dark:text-slate-400">
                            {{ $qte($lot->product, (float) $lot->quantity_remaining) }} · {{ $rythmeTxt($lot->product, $a['rythme']) }}
                            · {{ __('limite le :date', ['date' => $lot->expiry_date->format('d/m')]) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif

    @if ($analyse['perimes']->isEmpty() && $analyse['a_risque']->isEmpty() && $analyse['sans_date']->isEmpty() && $analyse['a_temps']->isEmpty())
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 py-16 text-center dark:border-emerald-900 dark:bg-emerald-900/20">
            <x-icon name="check" class="mx-auto h-8 w-8 text-emerald-500" />
            <p class="mt-2 font-medium text-emerald-800 dark:text-emerald-200">{{ __('Aucun lot proche de la péremption.') }}</p>
        </div>
    @endif
</div>
