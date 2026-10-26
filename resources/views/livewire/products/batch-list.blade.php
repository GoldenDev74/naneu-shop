@use('App\Support\Money')

<div class="space-y-4">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <x-illustration name="lots" />
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                    @if ($product)
                        {{ __('Lots —') }} {{ $product->name }}
                    @else
                        {{ __('Lots de stock') }}
                    @endif
                </h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ $lots->total() }} {{ __('lot(s) — ordre de consommation') }}
                    <span class="font-medium uppercase">{{ $strategie }}</span>
                    ({{ $strategie === 'fefo' ? __('premier périmé, premier sorti') : __('premier entré, premier sorti') }})
                </p>
            </div>
        </div>

        @if ($product && auth()->user()->can('update', $product))
            <a href="{{ route('produits.edit', $product) }}" wire:navigate
               class="btn-secondaire">
                {{ __('Fiche produit') }}
            </a>
        @endif
    </div>

    <div class="rounded-2xl border border-slate-200/70 bg-white shadow-doux p-3 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap gap-2">
            @unless ($product)
                <input type="search" wire:model.live.debounce.300ms="recherche"
                       placeholder="{{ __('N° de lot ou produit…') }}"
                       class="champ min-w-[14rem] flex-1">
            @endunless

            <select wire:model.live="filtre"
                    class="champ">
                <option value="disponibles">{{ __('Lots disponibles') }}</option>
                <option value="bientot">{{ __('Péremption proche') }}</option>
                <option value="perimes">{{ __('Lots périmés') }}</option>
                <option value="epuises">{{ __('Lots épuisés') }}</option>
                <option value="tous">{{ __('Tous les lots') }}</option>
            </select>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-entete">
                    <tr>
                        <x-table.th>{{ __('Ordre') }}</x-table.th>
                        <x-table.th filter="lot">{{ __('Lot') }}</x-table.th>
                        @unless ($product)
                            <x-table.th filter="produit">{{ __('Produit') }}</x-table.th>
                        @endunless
                        <x-table.th filter="recu_le" filter-type="date">{{ __('Reçu le') }}</x-table.th>
                        <x-table.th filter="peremption" filter-type="date">{{ __('Péremption') }}</x-table.th>
                        <x-table.th align="right" filter="recu" filter-type="number">{{ __('Reçu') }}</x-table.th>
                        <x-table.th align="right" filter="restant" filter-type="number">{{ __('Restant') }}</x-table.th>
                        @if ($voitPrix)
                            <x-table.th align="right" filter="prix" filter-type="number">{{ __('Prix d\'achat') }}</x-table.th>
                            <x-table.th align="right">{{ __('Valeur restante') }}</x-table.th>
                        @endif
                        <x-table.th filter="fournisseur">{{ __('Fournisseur') }}</x-table.th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($lots as $index => $lot)
                        @php
                            $jours = $lot->daysUntilExpiry();
                            $rang = $lots->firstItem() + $index;
                        @endphp

                        <tr wire:key="lot-{{ $lot->id }}"
                            class="{{ $lot->isDepleted() ? 'opacity-50' : '' }} hover:bg-slate-50 dark:hover:bg-slate-800/40">

                            <td class="px-4 py-3">
                                @if (! $lot->isDepleted())
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-marque-50 text-xs font-semibold text-marque-700 dark:bg-marque-900/30 dark:text-marque-300">
                                        {{ $rang }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 font-mono text-xs text-slate-700 dark:text-slate-200">
                                {{ $lot->batch_number }}
                            </td>

                            @unless ($product)
                                <td class="px-4 py-3">
                                    <a href="{{ route('produits.lots', $lot->product_id) }}" wire:navigate
                                       class="text-sm font-medium text-slate-900 hover:text-marque-600 dark:text-white dark:hover:text-marque-400">
                                        {{ $lot->product?->name }}
                                    </a>
                                    <p class="text-xs text-slate-400">{{ $lot->product?->sku }}</p>
                                </td>
                            @endunless

                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                {{ $lot->received_at->format('d/m/Y') }}
                            </td>

                            <td class="px-4 py-3">
                                @if ($lot->expiry_date)
                                    <span @class([
                                        'rounded px-2 py-0.5 text-xs font-medium',
                                        'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' => $jours !== null && $jours < 0,
                                        'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' => $jours !== null && $jours >= 0 && $jours <= 30,
                                        'text-slate-600 dark:text-slate-300' => $jours === null || $jours > 30,
                                    ])>
                                        {{ $lot->expiry_date->format('d/m/Y') }}
                                        @if ($jours !== null)
                                            <span class="ml-1 opacity-75">
                                                {{ $jours < 0 ? __('(périmé)') : '(' . __('J-:n', ['n' => $jours]) . ')' }}
                                            </span>
                                        @endif
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-500 dark:text-slate-400">
                                {{ rtrim(rtrim(\App\Support\Money::number((float) $lot->quantity_received, 3), '0'), ',.') }}
                            </td>

                            <td class="px-4 py-3 text-right">
                                <span @class([
                                    'rounded px-2 py-0.5 text-sm font-medium tabular-nums',
                                    'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => $lot->isDepleted(),
                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => ! $lot->isDepleted(),
                                ])>
                                    {{ rtrim(rtrim(\App\Support\Money::number((float) $lot->quantity_remaining, 3), '0'), ',.') }}
                                    {{ $lot->product?->unit?->symbol }}
                                </span>
                            </td>

                            @if ($voitPrix)
                                <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600 dark:text-slate-300">
                                    {{ Money::format($lot->purchase_price) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-medium tabular-nums text-slate-900 dark:text-white">
                                    {{ Money::format($lot->remainingValue()) }}
                                </td>
                            @endif

                            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                                {{ $lot->supplier?->name ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-16 text-center text-sm text-slate-500 dark:text-slate-400">
                                <x-empty-state illustration="lots" :message="__('Aucun lot ne correspond à ce filtre.')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($lots->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $lots->links() }}</div>
        @endif
    </div>
</div>
