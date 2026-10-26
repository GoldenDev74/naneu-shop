@use('App\Support\Money')

<div class="space-y-4">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <x-illustration name="produits" />
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Produits') }}</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ $produits->total() }} {{ __('produit(s) — catalogue') }} {{ config('naneu.shop.name') }}
                </p>
            </div>
        </div>

        @can('create', App\Models\Product::class)
            <a href="{{ route('produits.create') }}" wire:navigate
               class="btn-primaire">
                <x-icon name="plus" class="h-4 w-4" /> {{ __('Nouveau produit') }}
            </a>
        @endcan
    </div>

    {{-- ============ FILTRES ============ --}}
    <div class="rounded-2xl border border-slate-200/70 bg-white shadow-doux p-3 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap gap-2">

            <div class="relative min-w-[16rem] flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="search"
                       wire:model.live.debounce.300ms="recherche"
                       wire:keydown.enter="scanner"
                       placeholder="{{ __('Nom, référence ou scan du code-barres…') }}"
                       class="champ w-full pl-9 pr-3">
            </div>

            <select wire:model.live="categorie"
                    class="champ">
                <option value="">{{ __('Toutes les catégories') }}</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->optionLabel() }}</option>
                @endforeach
            </select>

            <select wire:model.live="statut"
                    class="champ">
                <option value="tous">{{ __('Tous les actifs') }}</option>
                <option value="rupture">{{ __('En rupture') }}</option>
                <option value="bas">{{ __('Stock bas') }}</option>
                <option value="reappro">{{ __('À réapprovisionner') }}</option>
                <option value="perissable">{{ __('Périssables') }}</option>
                <option value="inactif">{{ __('Désactivés') }}</option>
            </select>

            @if ($recherche || $categorie || $statut !== 'tous')
                <button wire:click="reinitialiser"
                        class="btn-secondaire">
                    {{ __('Réinitialiser') }}
                </button>
            @endif
        </div>
    </div>

    {{-- ============ TABLEAU ============ --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-entete">
                    <tr>
                        <x-table.th sortable column="name" :current="$tri" :direction="$sens" wire:click="trierPar('name')" filter="produit">{{ __('Produit') }}</x-table.th>
                        <x-table.th filter="categorie" filter-type="select" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->optionLabel()])->all()">{{ __('Catégorie') }}</x-table.th>
                        <x-table.th sortable column="quantity_on_hand" :current="$tri" :direction="$sens" wire:click="trierPar('quantity_on_hand')" align="right" filter="stock" filter-type="number">{{ __('Stock') }}</x-table.th>
                        @if ($voitPrix)
                            <x-table.th sortable column="sale_price" :current="$tri" :direction="$sens" wire:click="trierPar('sale_price')" align="right" filter="prix" filter-type="number">{{ __('Prix de vente') }}</x-table.th>
                        @endif
                        <x-table.th filter="perissable" filter-type="select" :options="['1' => __('Périssable'), '0' => __('Non périssable')]">{{ __('Péremption') }}</x-table.th>
                        <x-table.th align="right">{{ $peutAgir ? __('Actions') : '' }}</x-table.th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($produits as $produit)
                        <tr wire:key="produit-{{ $produit->id }}"
                            class="hover:bg-slate-50 dark:hover:bg-slate-800/40 {{ $produit->is_active ? '' : 'opacity-60' }}">

                            <td class="px-4 py-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    @php $photo = $produit->imageUrl(); @endphp
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100 ring-1 ring-inset ring-slate-200/70 dark:bg-slate-800 dark:ring-slate-700">
                                        @if ($photo)
                                            <img src="{{ $photo }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
                                        @else
                                            <x-icon name="package" class="h-5 w-5 text-slate-400" />
                                        @endif
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-slate-900 dark:text-white">{{ $produit->name }}</p>
                                        <p class="flex flex-wrap items-center gap-1.5 text-xs text-slate-400">
                                            <span>{{ $produit->sku }}</span>
                                            @if ($produit->barcode)
                                                <span class="inline-flex items-center gap-0.5">
                                                    <x-icon name="barcode" class="h-3 w-3" />{{ $produit->barcode }}
                                                </span>
                                            @endif
                                            @if ($produit->is_quick_item)
                                                <span class="rounded bg-indigo-100 px-1 text-[10px] font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                                    {{ __('vignette') }}
                                                </span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                @if ($produit->category)
                                    <span class="inline-flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300">
                                        <span class="h-2 w-2 rounded-full" style="background: {{ $produit->category->color }}"></span>
                                        {{ $produit->category->name }}
                                    </span>
                                @else
                                    <span class="text-sm text-slate-400">—</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-right">
                                <span class="inline-block rounded px-2 py-1 text-sm font-medium tabular-nums {{ $produit->stockStatusClasses() }}">
                                    {{ $produit->unit?->formatQuantity((float) $produit->quantity_on_hand)
                                       ?? rtrim(rtrim(\App\Support\Money::number((float) $produit->quantity_on_hand, 3), '0'), ',.') }}
                                </span>
                                <p class="mt-0.5 text-[11px] text-slate-400">
                                    {{ __('seuil') }} {{ rtrim(rtrim(\App\Support\Money::number((float) $produit->alert_quantity, 3), '0'), ',.') }}
                                </p>
                            </td>

                            @if ($voitPrix)
                                <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-700 dark:text-slate-200">
                                    {{ Money::format($produit->sale_price) }}
                                    @if ($produit->effectiveVatRate() > 0)
                                        <p class="text-[11px] text-slate-400">
                                            {{ __('HT') }} {{ Money::format($produit->salePriceExcludingVat()) }}
                                        </p>
                                    @elseif ($produit->is_vat_exempt && config('naneu.vat.enabled'))
                                        <p class="text-[11px] text-slate-400">{{ __('sans TVA') }}</p>
                                    @endif
                                </td>
                            @endif

                            <td class="px-4 py-3">
                                @php $proche = $produit->nearestExpiry(); @endphp

                                @if (! $produit->is_perishable)
                                    <span class="text-xs text-slate-400">{{ __('non périssable') }}</span>
                                @elseif ($proche)
                                    @php $jours = (int) now()->startOfDay()->diffInDays($proche->startOfDay(), false); @endphp
                                    <span @class([
                                        'rounded px-2 py-0.5 text-xs font-medium',
                                        'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' => $jours < 0,
                                        'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' => $jours >= 0 && $jours <= $produit->expiryAlertDays(),
                                        'text-slate-500 dark:text-slate-400' => $jours > $produit->expiryAlertDays(),
                                    ])>
                                        {{ $proche->format('d/m/Y') }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    @if (auth()->user()->canAccess('lots'))
                                        <a href="{{ route('produits.lots', $produit) }}" wire:navigate
                                           title="{{ __('Voir les lots') }}"
                                           class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700">
                                            <x-icon name="layers" class="h-4 w-4" />
                                        </a>
                                    @endif

                                    @can('update', $produit)
                                        <a href="{{ route('produits.edit', $produit) }}" wire:navigate
                                           title="{{ __('Modifier') }}"
                                           class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700">
                                            <x-icon name="pencil" class="h-4 w-4" />
                                        </a>

                                        <button wire:click="basculerActif({{ $produit->id }})"
                                                title="{{ $produit->is_active ? __('Désactiver') : __('Réactiver') }}"
                                                class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700">
                                            <x-icon :name="$produit->is_active ? 'x' : 'check'" class="h-4 w-4" />
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $voitPrix ? 6 : 5 }}" class="px-4 py-16 text-center">
                                @if ($filtresActifs)
                                    <x-empty-state illustration="produits" :message="__('Aucun produit ne correspond à ces filtres.')" />
                                    <button type="button" wire:click="reinitialiser"
                                            class="mt-2 inline-block text-sm font-medium text-marque-600 hover:underline dark:text-marque-400">
                                        {{ __('Réinitialiser les filtres') }}
                                    </button>
                                @else
                                    <x-empty-state illustration="produits" :message="__('Le catalogue est vide pour l\'instant.')" />
                                    @can('create', App\Models\Product::class)
                                        <a href="{{ route('produits.create') }}" wire:navigate
                                           class="mt-2 inline-block text-sm font-medium text-marque-600 hover:underline dark:text-marque-400">
                                            {{ __('Créer le premier produit') }}
                                        </a>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($produits->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">
                {{ $produits->links() }}
            </div>
        @endif
    </div>
</div>
