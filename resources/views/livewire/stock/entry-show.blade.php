@use('App\Support\Money')
@use('App\Enums\DocumentStatus')

<div class="mx-auto max-w-5xl space-y-5">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-center gap-4">
            <x-illustration name="entrees" />
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h1 class="font-mono text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $entry->reference }}</h1>
                    <span class="rounded px-2 py-0.5 text-xs font-medium {{ $entry->status->badgeClasses() }}">
                        {{ $entry->status->label() }}
                    </span>
                </div>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Reçu le') }} {{ $entry->entry_date->translatedFormat('j F Y') }}
                    @if ($entry->supplier) — {{ $entry->supplier->name }} @endif
                </p>
            </div>
        </div>

        <div class="flex gap-2">
            @if ($documentUrl)
                <a href="{{ $documentUrl }}" target="_blank" rel="noopener"
                   class="btn-secondaire">
                    <x-icon name="download" class="h-4 w-4" /> {{ __('Bon de livraison') }}
                </a>
            @endif

            @can('cancel', $entry)
                @if ($entry->status === DocumentStatus::Valide)
                    <button wire:click="$set('confirmationAnnulation', true)"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-400 dark:hover:bg-rose-900/20">
                        <x-icon name="x" class="h-4 w-4" /> {{ __('Annuler le bon') }}
                    </button>
                @endif
            @endcan

            <x-back-button :href="route('entrees.index')">{{ __('Retour') }}</x-back-button>
        </div>
    </div>

    {{-- ============ CONFIRMATION D'ANNULATION ============ --}}
    @if ($confirmationAnnulation)
        <div class="rounded-xl border border-rose-300 bg-rose-50 p-4 dark:border-rose-800 dark:bg-rose-900/20">
            <h2 class="font-semibold text-rose-900 dark:text-rose-200">{{ __('Annuler ce bon d\'entrée ?') }}</h2>
            <p class="mt-1 text-sm text-rose-800 dark:text-rose-300">
                {{ __('Les quantités reçues seront retirées du stock et un mouvement inverse sera inscrit au grand livre. L\'opération est refusée si l\'un des lots a déjà commencé à être vendu.') }}
            </p>

            <div class="mt-3 space-y-2">
                <input type="text" wire:model="motifAnnulation"
                       placeholder="{{ __('Motif de l\'annulation (obligatoire)') }}"
                       class="champ champ-erreur w-full">

                @error('motifAnnulation')
                    <p class="text-xs font-medium text-rose-700 dark:text-rose-300">{{ __($message) }}</p>
                @enderror

                <div class="flex gap-2">
                    <button wire:click="annuler"
                            class="btn-danger">
                        {{ __('Confirmer l\'annulation') }}
                    </button>
                    <button wire:click="$set('confirmationAnnulation', false)"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-white dark:border-slate-600 dark:text-slate-200">
                        {{ __('Revenir') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============ INFORMATIONS ============ --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <x-stat-card :label="__('Total du bon')" :value="Money::format($entry->total_cost)" />
        <x-stat-card :label="__('Lignes')" :value="$entry->lines->count()" />
        <x-stat-card :label="__('Fournisseur')" :value="$entry->supplier?->name ?? '—'" />
        <x-stat-card :label="__('Saisi par')" :value="$entry->user?->name ?? '—'" />
    </div>

    {{-- ============ LIGNES ET LOTS ============ --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
            <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Produits reçus') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('Chaque ligne a créé un lot, consommé ensuite dans l\'ordre FIFO.') }}
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-entete">
                    <tr>
                        <x-table.th>{{ __('Produit') }}</x-table.th>
                        <x-table.th>{{ __('Lot') }}</x-table.th>
                        <x-table.th align="right">{{ __('Quantité reçue') }}</x-table.th>
                        <x-table.th align="right">{{ __('Restant') }}</x-table.th>
                        <x-table.th align="right">{{ __('Prix d\'achat') }}</x-table.th>
                        <x-table.th>{{ __('Péremption') }}</x-table.th>
                        <x-table.th align="right">{{ __('Total') }}</x-table.th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($entry->lines as $ligne)
                        <tr wire:key="ligne-{{ $ligne->id }}">
                            <td class="px-4 py-3">
                                @if (auth()->user()->canAccess('lots'))
                                    <a href="{{ route('produits.lots', $ligne->product_id) }}" wire:navigate
                                       class="font-medium text-slate-900 hover:text-marque-600 dark:text-white dark:hover:text-marque-400">
                                        {{ $ligne->product?->name ?? __('Produit supprimé') }}
                                    </a>
                                @else
                                    <span class="font-medium text-slate-900 dark:text-white">{{ $ligne->product?->name ?? __('Produit supprimé') }}</span>
                                @endif
                                <p class="text-xs text-slate-400">{{ $ligne->product?->sku }}</p>
                            </td>

                            <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">
                                {{ $ligne->batch_number ?? '—' }}
                            </td>

                            <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-700 dark:text-slate-200">
                                {{ $ligne->product?->unit?->formatQuantity((float) $ligne->quantity)
                                   ?? rtrim(rtrim(\App\Support\Money::number((float) $ligne->quantity, 3), '0'), ',.') }}
                            </td>

                            <td class="px-4 py-3 text-right text-sm tabular-nums">
                                @if ($ligne->batch)
                                    <span @class([
                                        'rounded px-2 py-0.5',
                                        'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => $ligne->batch->isDepleted(),
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => ! $ligne->batch->isDepleted(),
                                    ])>
                                        {{ rtrim(rtrim(\App\Support\Money::number((float) $ligne->batch->quantity_remaining, 3), '0'), ',.') }}
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-700 dark:text-slate-200">
                                {{ Money::format($ligne->purchase_price) }}
                            </td>

                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                {{ $ligne->expiry_date?->format('d/m/Y') ?? '—' }}
                            </td>

                            <td class="px-4 py-3 text-right text-sm font-medium tabular-nums text-slate-900 dark:text-white">
                                {{ Money::format($ligne->line_total) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot class="bg-slate-50 dark:bg-slate-800/50">
                    <tr>
                        <td colspan="6" class="px-4 py-3 text-right text-sm font-medium text-slate-600 dark:text-slate-300">
                            {{ __('Total') }}
                        </td>
                        <td class="px-4 py-3 text-right text-base font-semibold tabular-nums text-slate-900 dark:text-white">
                            {{ Money::format($entry->total_cost) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @if ($frais->isNotEmpty())
        @php $totalFrais = $frais->sum('amount'); @endphp
        <div class="rounded-2xl border border-slate-200/70 bg-white shadow-doux p-4 dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                    <x-icon name="truck" class="h-4 w-4 text-slate-400" /> {{ __('Frais liés à cette livraison') }}
                </h2>
                @if ($voitCompta)
                    <a href="{{ route('comptabilite.index', ['depuis' => $entry->entry_date->copy()->startOfMonth()->toDateString(), 'jusqua' => $entry->entry_date->copy()->endOfMonth()->toDateString()]) }}" wire:navigate
                       class="text-xs font-medium text-marque-700 hover:underline dark:text-marque-300">{{ __('Voir en comptabilité') }}</a>
                @endif
            </div>
            <ul class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                @foreach ($frais as $f)
                    <li class="flex items-baseline justify-between gap-3 py-1.5">
                        <span class="text-slate-600 dark:text-slate-300">{{ $f->label }}</span>
                        <span class="font-medium tabular-nums text-slate-900 dark:text-white">{{ Money::format($f->amount) }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 border-t border-dashed border-slate-200 pt-2 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">
                {{ __('Marchandise + frais : :total, soit :taux % de frais en plus.', [
                    'total' => Money::format($entry->total_cost + $totalFrais),
                    'taux'  => $entry->total_cost > 0 ? round($totalFrais / $entry->total_cost * 100, 1) : 0,
                ]) }}
            </p>
        </div>
    @endif

    @if ($entry->note)
        <div class="rounded-2xl border border-slate-200/70 bg-white shadow-doux p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-1 text-sm font-semibold text-slate-900 dark:text-white">{{ __('Note') }}</h2>
            <p class="whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ $entry->note }}</p>
        </div>
    @endif
</div>
