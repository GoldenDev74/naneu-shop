@use('App\Support\Money')

<div class="space-y-4">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <x-illustration name="sorties" />
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Sorties de stock') }}</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ $sorties->total() }} {{ __('bon(s) — ventes, pertes et ajustements') }}
                </p>
            </div>
        </div>

        @can('create', App\Models\StockExit::class)
            <a href="{{ route('sorties.create') }}" wire:navigate
               class="btn-primaire">
                <x-icon name="plus" class="h-4 w-4" /> {{ __('Nouvelle sortie') }}
            </a>
        @endcan
    </div>

    <div class="rounded-2xl border border-slate-200/70 bg-white shadow-doux p-3 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap gap-2">
            <input type="search" wire:model.live.debounce.300ms="recherche" placeholder="{{ __('Référence…') }}"
                   class="champ min-w-[12rem] flex-1">

            <select wire:model.live="type"
                    class="champ">
                <option value="">{{ __('Tous les motifs') }}</option>
                @foreach ($types as $valeur => $libelle)
                    <option value="{{ $valeur }}">{{ $libelle }}</option>
                @endforeach
            </select>

            <select wire:model.live="origine" class="champ">
                <option value="">{{ __('Toutes origines') }}</option>
                <option value="caisse">{{ __('Caisse') }}</option>
                <option value="stock">{{ __('Bons de sortie') }}</option>
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
                        <x-table.th filter="reference">{{ __('Référence') }}</x-table.th>
                        <x-table.th filter="date" filter-type="date">{{ __('Date') }}</x-table.th>
                        <x-table.th filter="motif" filter-type="select" :options="$types">{{ __('Motif') }}</x-table.th>
                        <x-table.th align="right">{{ __('Lignes') }}</x-table.th>
                        @if ($voitPrix)
                            <x-table.th align="right" filter="cout" filter-type="number">{{ __('Coût') }}</x-table.th>
                            <x-table.th align="right" filter="vente" filter-type="number">{{ __('Vente') }}</x-table.th>
                            <x-table.th align="right" filter="marge" filter-type="number">{{ __('Marge') }}</x-table.th>
                        @endif
                        <x-table.th filter="auteur">{{ __('Par') }}</x-table.th>
                        <x-table.th filter="statut" filter-type="select" :options="collect(App\Enums\DocumentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()">{{ __('Statut') }}</x-table.th>
                        <x-table.th><span class="sr-only">{{ __('Aperçu') }}</span></x-table.th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($sorties as $sortie)
                        <tr wire:key="sortie-{{ $sortie->id }}"
                            class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/40"
                            onclick="window.location='{{ route('sorties.show', $sortie) }}'">

                            <td class="px-4 py-3 font-mono text-sm font-medium text-slate-900 dark:text-white">
                                {{ $sortie->reference }}
                            </td>

                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                {{ $sortie->exit_date->format('d/m/Y') }}
                            </td>

                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-xs font-medium {{ $sortie->type->badgeClasses() }}">
                                    {{ $sortie->type->label() }}
                                </span>
                                @if ($sortie->payment_method)
                                    <span class="ml-1 inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300" title="{{ __('Encaissée à la caisse') }}">
                                        <x-icon name="shopping-basket" class="h-3 w-3" /> {{ __('Caisse') }}
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600 dark:text-slate-300">
                                {{ $sortie->lines_count }}
                            </td>

                            @if ($voitPrix)
                                <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600 dark:text-slate-300">
                                    {{ Money::format($sortie->total_cost) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-medium tabular-nums text-slate-900 dark:text-white">
                                    {{ $sortie->type->generatesRevenue() ? Money::format($sortie->total_sale) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-medium tabular-nums">
                                    @if ($sortie->type->generatesRevenue())
                                        <span class="{{ $sortie->total_margin >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                            {{ Money::format($sortie->total_margin) }}
                                        </span>
                                    @else
                                        <span class="text-rose-600 dark:text-rose-400">
                                            −{{ Money::format($sortie->total_cost) }}
                                        </span>
                                    @endif
                                </td>
                            @endif

                            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                                {{ $sortie->user?->name ?? '—' }}
                            </td>

                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-xs font-medium {{ $sortie->status->badgeClasses() }}">
                                    {{ $sortie->status->label() }}
                                </span>
                            </td>

                            <td class="px-2 py-3 text-right" onclick="event.stopPropagation()">
                                <x-bouton-oeil wire:click="voir({{ $sortie->id }})" :label="__('Aperçu de :ref', ['ref' => $sortie->reference])" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $voitPrix ? 10 : 7 }}" class="px-4 py-16 text-center text-sm text-slate-500 dark:text-slate-400">
                                <x-empty-state illustration="sorties" :message="__('Aucune sortie enregistrée.')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($sorties->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $sorties->links() }}</div>
        @endif
    </div>

    @if ($detail)
        @include('livewire.stock.partials.apercu-sortie', ['sortie' => $detail])
    @endif
</div>
