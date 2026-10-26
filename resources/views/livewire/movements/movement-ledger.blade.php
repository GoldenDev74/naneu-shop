@use('App\Support\Money')

<div class="space-y-4">

    <div class="flex items-center gap-4">
        <x-illustration name="mouvements" />
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Mouvements de stock') }}</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                {{ __('Grand livre en ajout seul :') }} {{ \App\Support\Money::number($mouvements->total(), 0) }} {{ __('écriture(s). Une erreur se corrige par un mouvement inverse, jamais par une modification.') }}
            </p>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200/70 bg-white shadow-doux p-3 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap gap-2">
            <input type="search" wire:model.live.debounce.300ms="recherche" placeholder="{{ __('Produit…') }}"
                   class="champ min-w-[12rem] flex-1">

            <select wire:model.live="sens"
                    class="champ">
                <option value="">{{ __('Entrées et sorties') }}</option>
                <option value="in">{{ __('Entrées') }}</option>
                <option value="out">{{ __('Sorties') }}</option>
            </select>

            <select wire:model.live="motif"
                    class="champ">
                <option value="">{{ __('Tous les motifs') }}</option>
                @foreach ($motifs as $valeur => $libelle)
                    <option value="{{ $valeur }}">{{ $libelle }}</option>
                @endforeach
            </select>

            <input type="date" wire:model.live="depuis"
                   class="champ">
            <input type="date" wire:model.live="jusqua"
                   class="champ">
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-entete">
                    <tr>
                        <x-table.th filter="date" filter-type="date">{{ __('Date') }}</x-table.th>
                        <x-table.th filter="sens" filter-type="select" :options="['in' => __('Entrée'), 'out' => __('Sortie')]">{{ __('Sens') }}</x-table.th>
                        <x-table.th filter="produit">{{ __('Produit') }}</x-table.th>
                        <x-table.th filter="lot">{{ __('Lot') }}</x-table.th>
                        <x-table.th filter="motif" filter-type="select" :options="$motifs">{{ __('Motif') }}</x-table.th>
                        <x-table.th align="right" filter="quantite" filter-type="number">{{ __('Quantité') }}</x-table.th>
                        <x-table.th align="right" filter="stock_apres" filter-type="number">{{ __('Stock après') }}</x-table.th>
                        @if ($voitPrix)
                            <x-table.th align="right" filter="cout" filter-type="number">{{ __('Coût') }}</x-table.th>
                        @endif
                        <x-table.th filter="auteur">{{ __('Par') }}</x-table.th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($mouvements as $mouvement)
                        <tr wire:key="mvt-{{ $mouvement->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">

                            <td class="whitespace-nowrap px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300">
                                {{ $mouvement->occurred_at->format('d/m/Y') }}
                                <span class="text-xs text-slate-400">{{ $mouvement->occurred_at->format('H:i') }}</span>
                            </td>

                            <td class="px-4 py-2.5">
                                <span class="rounded px-2 py-0.5 text-xs font-medium {{ $mouvement->direction->badgeClasses() }}">
                                    {{ $mouvement->direction->label() }}
                                </span>
                            </td>

                            <td class="px-4 py-2.5">
                                <a @if (auth()->user()->canAccess('lots')) href="{{ route('produits.lots', $mouvement->product_id) }}" wire:navigate @endif
                                   class="text-sm font-medium text-slate-900 hover:text-marque-600 dark:text-white dark:hover:text-marque-400">
                                    {{ $mouvement->product?->name }}
                                </a>
                                <p class="text-xs text-slate-400">{{ $mouvement->product?->sku }}</p>
                            </td>

                            <td class="px-4 py-2.5 font-mono text-xs text-slate-500 dark:text-slate-400">
                                {{ $mouvement->batch?->batch_number ?? '—' }}
                            </td>

                            <td class="px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300">
                                {{ $mouvement->reason->label() }}
                            </td>

                            <td class="px-4 py-2.5 text-right text-sm font-medium tabular-nums {{ $mouvement->direction->value === 'in' ? 'text-emerald-600 dark:text-emerald-400' : 'text-orange-600 dark:text-orange-400' }}">
                                {{ $mouvement->direction->value === 'in' ? '+' : '−' }}{{ rtrim(rtrim(\App\Support\Money::number((float) $mouvement->quantity, 3), '0'), ',.') }}
                                <span class="text-xs text-slate-400">{{ $mouvement->product?->unit?->symbol }}</span>
                            </td>

                            <td class="px-4 py-2.5 text-right text-sm tabular-nums text-slate-500 dark:text-slate-400">
                                {{ rtrim(rtrim(\App\Support\Money::number((float) $mouvement->quantity_after, 3), '0'), ',.') }}
                            </td>

                            @if ($voitPrix)
                                <td class="px-4 py-2.5 text-right text-sm tabular-nums text-slate-600 dark:text-slate-300">
                                    {{ Money::format($mouvement->total_cost) }}
                                </td>
                            @endif

                            <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">
                                {{ $mouvement->user?->name ?? __('système') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $voitPrix ? 9 : 8 }}" class="px-4 py-16 text-center text-sm text-slate-500 dark:text-slate-400">
                                {{ __('Aucun mouvement pour ces critères.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($mouvements->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $mouvements->links() }}</div>
        @endif
    </div>
</div>
