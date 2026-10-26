@use('App\Support\Money')

<div class="space-y-5">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <x-illustration name="sorties" />
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Nouvelle sortie de stock') }}</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Bon') }} {{ $reference }} ({{ __('provisoire') }}) — {{ __('les lots les plus anciens sont consommés en premier.') }}
                </p>
            </div>
        </div>

        <x-back-button :href="route('sorties.index')">{{ __('Retour aux sorties') }}</x-back-button>
    </div>

    <form wire:submit="enregistrer" class="space-y-5">

        <x-form.card icon="file-text" :title="__('Informations du bon')">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-form.field :label="__('Motif de la sortie')" name="type" required>
                    <select wire:model.live="type"
                            class="champ w-full">
                        @foreach ($typesAutorises as $valeur => $libelle)
                            <option value="{{ $valeur }}">{{ $libelle }}</option>
                        @endforeach
                    </select>
                </x-form.field>

                <x-form.field :label="__('Date')" name="exit_date" required>
                    <input type="date" wire:model="exit_date" max="{{ now()->toDateString() }}"
                           class="champ w-full">
                </x-form.field>

                <div class="flex items-end pb-1">
                    @if (! $estVente)
                        <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                            {{ __('Sortie sans chiffre d\'affaires : seul le coût d\'achat des lots sera imputé.') }}
                        </p>
                    @endif
                </div>
            </div>
        </x-form.card>

        {{-- ============ VIGNETTES RAPIDES ============ --}}
        @if ($vignettes->isNotEmpty())
            <x-form.card icon="scan-barcode" :title="__('Produits sans code-barres')"
                         :description="__('Produits pesés ou vendus au détail : un clic suffit, aucune étiquette nécessaire.')">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                    @foreach ($vignettes as $vignette)
                        @php
                            $epuise = (float) $vignette->vendable <= 0;
                            $ligne = collect($lignes)->firstWhere('product_id', $vignette->id);
                            $photo = $vignette->imageUrl();
                        @endphp
                        {{-- Épuisé : grisé et désactivé. Déjà dans la sortie : bordure et quantité. --}}
                        <button type="button"
                                wire:key="vignette-{{ $vignette->id }}"
                                wire:click="ajouterProduit({{ $vignette->id }})"
                                @disabled($epuise)
                                @class([
                                    'group relative flex flex-col overflow-hidden rounded-2xl border bg-white text-left transition duration-150 dark:bg-slate-900',
                                    'hover:-translate-y-0.5 hover:shadow-doux' => ! $epuise,
                                    'cursor-not-allowed opacity-50' => $epuise,
                                    'border-marque-500 ring-2 ring-marque-500/20' => $ligne,
                                    'border-slate-200 hover:border-marque-300 dark:border-slate-700' => ! $ligne,
                                ])>
                            <span class="block aspect-[4/3] w-full overflow-hidden bg-slate-100 dark:bg-slate-800">
                                @if ($photo)
                                    <img src="{{ $photo }}" alt="" loading="lazy" decoding="async"
                                         class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                                @else
                                    <span class="flex h-full items-center justify-center">
                                        <x-icon name="package" class="h-8 w-8 text-slate-300" />
                                    </span>
                                @endif
                            </span>

                            @if ($ligne)
                                <span class="absolute right-2 top-2 flex h-6 min-w-6 items-center justify-center rounded-full bg-marque-600 px-1.5 text-xs font-bold tabular-nums text-white shadow">
                                    {{ rtrim(rtrim(\App\Support\Money::number((float) $ligne['quantity'], 3), '0'), ',.') }}
                                </span>
                            @endif

                            <span class="flex flex-1 flex-col gap-1 p-2.5">
                                <span class="line-clamp-2 text-[13px] font-semibold leading-snug text-slate-800 dark:text-slate-100">{{ $vignette->name }}</span>
                                <span class="mt-auto flex items-baseline justify-between gap-2 text-xs">
                                    <span class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ \App\Support\Money::format($vignette->sale_price) }}</span>
                                    <span @class(['tabular-nums', 'text-rose-500' => $epuise, 'text-slate-400' => ! $epuise])>
                                        @if ($epuise && $vignette->perime > 0)
                                            {{ __('Périmé') }}
                                        @else
                                            {{ rtrim(rtrim(\App\Support\Money::number((float) $vignette->vendable, 3), '0'), ',.') }}
                                            {{ $vignette->unit?->symbol }}
                                        @endif
                                    </span>
                                </span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </x-form.card>
        @endif

        {{-- ============ PRODUITS ============ --}}
        <x-form.card icon="package-minus" :title="__('Produits sortis')">

            <div class="relative" x-data="{ ouvert: false }" @click.outside="ouvert = false">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />

                <input type="text"
                       wire:model.live.debounce.250ms="recherche"
                       wire:keydown.enter.prevent="scanner"
                       @focus="ouvert = true" @input="ouvert = true"
                       placeholder="{{ __('Scanner un code-barres ou taper un nom…') }}"
                       autocomplete="off"
                       class="champ w-full pl-9 pr-3">

                @if ($resultats->isNotEmpty())
                    <div x-show="ouvert" x-transition
                         class="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800">
                        @foreach ($resultats as $produit)
                            <button type="button"
                                    wire:key="res-{{ $produit->id }}"
                                    wire:click="ajouterProduit({{ $produit->id }})"
                                    @click="ouvert = false"
                                    @class([
                                        'flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-700',
                                        'opacity-50' => $produit->isOutOfStock(),
                                    ])>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-slate-900 dark:text-white">{{ $produit->name }}</span>
                                    <span class="block text-xs text-slate-400">{{ $produit->sku }}</span>
                                </span>
                                <span class="shrink-0 rounded px-1.5 py-0.5 text-xs tabular-nums {{ $produit->stockStatusClasses() }}">
                                    {{ rtrim(rtrim(\App\Support\Money::number((float) $produit->quantity_on_hand, 3), '0'), ',.') }}
                                    {{ $produit->unit?->symbol }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            @error('lignes')
                <p class="mt-2 text-sm font-medium text-rose-600 dark:text-rose-400">{{ __($message) }}</p>
            @enderror

            @if ($lignes)
                <div class="mt-4 space-y-3">
                    @foreach ($lignes as $index => $ligne)
                        @php
                            $detail = $apercu[$index] ?? ['prelevements' => [], 'cout' => 0, 'manquant' => 0];
                            $quantite = (float) str_replace(',', '.', (string) $ligne['quantity']);
                        @endphp

                        <div wire:key="ligne-{{ $index }}-{{ $ligne['product_id'] }}"
                             class="rounded-lg border border-slate-200 p-3 dark:border-slate-800">

                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-slate-900 dark:text-white">{{ $ligne['nom'] }}</p>
                                    <p class="text-xs text-slate-400">
                                        {{ $ligne['sku'] }} · {{ __('stock') }}
                                        {{ rtrim(rtrim(\App\Support\Money::number($ligne['stock'], 3), '0'), ',.') }}
                                        {{ $ligne['unite'] }}
                                    </p>
                                </div>

                                <div class="flex items-end gap-3">
                                    <div>
                                        <label class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Quantité') }}</label>
                                        <div class="flex items-center gap-1">
                                            <input type="text"
                                                   wire:model.live.debounce.400ms="lignes.{{ $index }}.quantity"
                                                   inputmode="decimal"
                                                   class="champ-sm w-24 text-right tabular-nums">
                                            <span class="w-8 text-xs text-slate-400">{{ $ligne['unite'] }}</span>
                                        </div>
                                    </div>

                                    @if ($estVente)
                                        <div>
                                            <label class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Prix unitaire') }}</label>
                                            <input type="text"
                                                   wire:model.live.debounce.400ms="lignes.{{ $index }}.sale_price"
                                                   inputmode="numeric"
                                                   class="champ-sm w-28 text-right tabular-nums">
                                            @php $catalogue = (int) ($ligne['catalogue'] ?? 0); @endphp
                                            @if ($catalogue > 0 && Money::parse($ligne['sale_price']) !== $catalogue)
                                                <p class="mt-0.5 text-xs text-amber-600 dark:text-amber-400">{{ __('Catalogue : :prix', ['prix' => Money::format($catalogue)]) }}</p>
                                            @endif
                                            @error("lignes.{$index}.sale_price")
                                                <p class="mt-0.5 max-w-[14rem] text-xs text-rose-600">{{ __($message) }}</p>
                                            @enderror
                                        </div>
                                    @endif

                                    <button type="button" wire:click="retirerLigne({{ $index }})"
                                            class="mb-1 rounded p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-900/30">
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            @error("lignes.{$index}.quantity")
                                <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ __($message) }}</p>
                            @enderror

                            {{-- ---- Aperçu FIFO ---- --}}
                            @if ($detail['prelevements'])
                                <div class="mt-2 rounded-md bg-slate-50 p-2 dark:bg-slate-800/60">
                                    <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                        {{ __('Lots consommés (FIFO)') }}
                                    </p>

                                    <div class="space-y-0.5">
                                        @foreach ($detail['prelevements'] as $prelevement)
                                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                                <span class="font-mono text-slate-600 dark:text-slate-300">{{ $prelevement['lot'] }}</span>

                                                <span class="tabular-nums text-slate-700 dark:text-slate-200">
                                                    {{ rtrim(rtrim(\App\Support\Money::number($prelevement['quantite'], 3), '0'), ',.') }}
                                                    {{ $ligne['unite'] }}
                                                </span>

                                                <span class="text-slate-400">à {{ Money::format($prelevement['prix']) }}</span>

                                                @if ($prelevement['peremption'])
                                                    <span @class([
                                                        'rounded px-1.5 py-0.5',
                                                        'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' => $prelevement['jours'] !== null && $prelevement['jours'] < 0,
                                                        'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' => $prelevement['jours'] !== null && $prelevement['jours'] >= 0 && $prelevement['jours'] <= 7,
                                                        'text-slate-400' => $prelevement['jours'] === null || $prelevement['jours'] > 7,
                                                    ])>
                                                        {{ __('exp.') }} {{ $prelevement['peremption'] }}
                                                    </span>
                                                @endif

                                                @if ($prelevement['vide'])
                                                    <span class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                                        {{ __('lot épuisé') }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>

                                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                                        {{ __('Coût d\'achat :') }} <span class="font-medium tabular-nums">{{ Money::format($detail['cout']) }}</span>
                                        @if ($estVente)
                                            · {{ __('Marge :') }}
                                            @php $marge = (int) round($quantite * Money::parse($ligne['sale_price'])) - $detail['cout']; @endphp
                                            <span class="font-medium tabular-nums {{ $marge >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                                {{ Money::format($marge) }}
                                            </span>
                                        @endif
                                    </p>
                                </div>
                            @endif

                            @if ($detail['manquant'] > 0)
                                <p class="mt-2 rounded-md bg-rose-50 px-2 py-1.5 text-xs font-medium text-rose-700 dark:bg-rose-900/20 dark:text-rose-300">
                                    {{ __('Stock insuffisant : il manque') }}
                                    {{ rtrim(rtrim(\App\Support\Money::number($detail['manquant'], 3), '0'), ',.') }}
                                    {{ $ligne['unite'] }}.
                                </p>
                            @endif

                            @if (($detail['perime'] ?? 0) > 0)
                                <p class="mt-2 flex items-center gap-1.5 rounded-md bg-amber-50 px-2 py-1.5 text-xs font-medium text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                                    <x-icon name="circle-alert" class="h-3.5 w-3.5 shrink-0" />
                                    {{ __(':qte périmé(s) en stock : vente bloquée, retirez-les du rayon.', ['qte' => rtrim(rtrim(\App\Support\Money::number($detail['perime'], 3), '0'), ',.').' '.$ligne['unite']]) }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <x-empty-state illustration="sorties" :message="__('Aucun produit sélectionné.')"
                               :hint="__('Scannez un code-barres ou cherchez par nom ci-dessus.')"
                               class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 py-10 dark:border-slate-700 dark:bg-slate-800/30" />
            @endif
        </x-form.card>

        <x-form.card icon="notebook-pen" :title="__('Note')">
            <textarea wire:model="note" rows="2"
                      placeholder="{{ $estVente ? __('Référence client, observation…') : __('Motif détaillé de la perte ou de l\'ajustement…') }}"
                      class="champ w-full"></textarea>
            @error('note')
                <p class="mt-1 text-xs text-rose-600">{{ __($message) }}</p>
            @enderror
        </x-form.card>

        {{-- ============ RÉCAPITULATIF ============ --}}
        <div class="barre-actions">
            <div class="flex flex-wrap gap-8">
                <div>
                    <p class="libelle">{{ __('Coût d\'achat') }}</p>
                    <p class="valeur">{{ Money::format($totalCout) }}</p>
                </div>

                @if ($estVente)
                    @php $marge = $totalVente - $totalTva - $totalCout; @endphp
                    <div>
                        <p class="libelle">{{ __('Total TTC') }}</p>
                        <p class="valeur">{{ Money::format($totalVente) }}</p>
                    </div>
                    <div>
                        <p class="libelle">{{ __('Dont TVA') }}</p>
                        <p class="valeur text-slate-500 dark:text-slate-400">{{ Money::format($totalTva) }}</p>
                    </div>
                    <div>
                        <p class="libelle">{{ __('Marge') }}</p>
                        <p class="valeur {{ $marge >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            {{ Money::format($marge) }}
                        </p>
                    </div>
                @endif
            </div>

            <div class="flex gap-3">
                <a href="{{ route('sorties.index') }}" wire:navigate
                   class="btn-secondaire">
                    {{ __('Annuler') }}
                </a>

                <button type="submit" wire:loading.attr="disabled" @disabled(! $lignes)
                        class="btn-primaire">
                    <span wire:loading.remove wire:target="enregistrer">{{ __('Valider la sortie') }}</span>
                    <span wire:loading wire:target="enregistrer">{{ __('Enregistrement…') }}</span>
                </button>
            </div>
        </div>
    </form>
</div>
