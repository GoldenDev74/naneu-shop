@use('App\Support\Money')

<div class="space-y-4">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <x-illustration name="entrees" />
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Entrées de stock') }}</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ $entrees->total() }} {{ __('bon(s) de réception') }}
                </p>
            </div>
        </div>

        @can('create', App\Models\StockEntry::class)
            <a href="{{ route('entrees.create') }}" wire:navigate
               class="btn-primaire">
                <x-icon name="plus" class="h-4 w-4" /> {{ __('Nouvelle entrée') }}
            </a>
        @endcan
    </div>

    <div class="rounded-2xl border border-slate-200/70 bg-white shadow-doux p-3 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap gap-2">
            <input type="search" wire:model.live.debounce.300ms="recherche"
                   placeholder="{{ __('Référence ou n° de facture…') }}"
                   class="champ min-w-[14rem] flex-1">

            <select wire:model.live="fournisseur"
                    class="champ">
                <option value="">{{ __('Tous les fournisseurs') }}</option>
                @foreach ($fournisseurs as $f)
                    <option value="{{ $f->id }}">{{ $f->name }}</option>
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
                        <x-table.th filter="reference">{{ __('Référence') }}</x-table.th>
                        <x-table.th filter="date" filter-type="date">{{ __('Date') }}</x-table.th>
                        <x-table.th filter="fournisseur" filter-type="select" :options="$fournisseurs->pluck('name', 'id')->all()">{{ __('Fournisseur') }}</x-table.th>
                        <x-table.th align="right">{{ __('Lignes') }}</x-table.th>
                        <x-table.th align="right" filter="total" filter-type="number">{{ __('Total') }}</x-table.th>
                        <x-table.th filter="auteur">{{ __('Saisi par') }}</x-table.th>
                        <x-table.th filter="statut" filter-type="select" :options="collect(App\Enums\DocumentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()">{{ __('Statut') }}</x-table.th>
                        <x-table.th><span class="sr-only">{{ __('Aperçu') }}</span></x-table.th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($entrees as $entree)
                        <tr wire:key="entree-{{ $entree->id }}"
                            class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/40"
                            onclick="window.location='{{ route('entrees.show', $entree) }}'">

                            <td class="px-4 py-3 font-mono text-sm font-medium text-slate-900 dark:text-white">
                                {{ $entree->reference }}
                                @if ($entree->invoice_number)
                                    <span class="block text-xs font-sans text-slate-400">{{ __('fact.') }} {{ $entree->invoice_number }}</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                {{ $entree->entry_date->format('d/m/Y') }}
                            </td>

                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                {{ $entree->supplier?->name ?? '—' }}
                            </td>

                            <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600 dark:text-slate-300">
                                {{ $entree->lines_count }}
                            </td>

                            <td class="px-4 py-3 text-right text-sm font-medium tabular-nums text-slate-900 dark:text-white">
                                {{ Money::format($entree->total_cost) }}
                            </td>

                            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                                {{ $entree->user?->name ?? '—' }}
                            </td>

                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-xs font-medium {{ $entree->status->badgeClasses() }}">
                                    {{ $entree->status->label() }}
                                </span>
                            </td>

                            <td class="px-2 py-3 text-right" onclick="event.stopPropagation()">
                                <x-bouton-oeil wire:click="voir({{ $entree->id }})" :label="__('Aperçu de :ref', ['ref' => $entree->reference])" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-16 text-center text-sm text-slate-500 dark:text-slate-400">
                                <x-empty-state illustration="entrees" :message="__('Aucune entrée enregistrée.')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($entrees->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $entrees->links() }}</div>
        @endif
    </div>

    @if ($detail)
        @include('livewire.stock.partials.apercu-entree', ['entree' => $detail])
    @endif
</div>
