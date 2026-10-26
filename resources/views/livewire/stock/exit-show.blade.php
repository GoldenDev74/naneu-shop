@use('App\Support\Money')
@use('App\Enums\DocumentStatus')

<div class="mx-auto max-w-5xl space-y-5">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-center gap-4">
            <x-illustration name="sorties" />
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="font-mono text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $exit->reference }}</h1>
                    <span class="rounded px-2 py-0.5 text-xs font-medium {{ $exit->type->badgeClasses() }}">
                        {{ $exit->type->label() }}
                    </span>
                    <span class="rounded px-2 py-0.5 text-xs font-medium {{ $exit->status->badgeClasses() }}">
                        {{ $exit->status->label() }}
                    </span>
                </div>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ $exit->exit_date->translatedFormat('j F Y') }} — {{ __('saisi par') }} {{ $exit->user?->name ?? '—' }}
                </p>
            </div>
        </div>

        <div class="flex gap-2">
            @can('cancel', $exit)
                @if ($exit->status === DocumentStatus::Valide)
                    <button wire:click="$set('confirmationAnnulation', true)"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-400 dark:hover:bg-rose-900/20">
                        <x-icon name="x" class="h-4 w-4" /> {{ __('Annuler le bon') }}
                    </button>
                @endif
            @endcan

            @if ($exit->type === \App\Enums\ExitType::Vente)
                <a href="{{ route('caisse.ticket', $exit) }}" target="_blank" class="btn-secondaire">
                    <x-icon name="printer" class="h-4 w-4" /> {{ __('Ticket') }}
                </a>
            @endif

            <x-back-button :href="route('sorties.index')">{{ __('Retour') }}</x-back-button>
        </div>
    </div>

    @if ($confirmationAnnulation)
        <div class="rounded-xl border border-rose-300 bg-rose-50 p-4 dark:border-rose-800 dark:bg-rose-900/20">
            <h2 class="font-semibold text-rose-900 dark:text-rose-200">{{ __('Annuler cette sortie ?') }}</h2>
            <p class="mt-1 text-sm text-rose-800 dark:text-rose-300">
                {{ __('Les quantités seront remises dans leurs lots d\'origine, au prix d\'achat d\'origine. Un mouvement inverse sera inscrit au grand livre.') }}
            </p>

            <div class="mt-3 space-y-2">
                <input type="text" wire:model="motifAnnulation" placeholder="{{ __('Motif de l\'annulation (obligatoire)') }}"
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

    @if ($voitPrix)
        <div class="grid gap-3 sm:grid-cols-3">
            <x-stat-card :label="__('Coût d\'achat (FIFO)')" :value="Money::format($exit->total_cost)" />
            <x-stat-card :label="__('Chiffre d\'affaires TTC')"
                         :value="$exit->type->generatesRevenue() ? Money::format($exit->total_sale) : '—'"
                         :hint="$exit->type->generatesRevenue() ? __('Dont TVA :tva', ['tva' => Money::format($exit->total_vat)]) : null" />
            <x-stat-card :label="__('Marge')"
                         :value="$exit->type->generatesRevenue() ? Money::format($exit->total_margin) : '−' . Money::format($exit->total_cost)"
                         :tone="$exit->type->generatesRevenue() && $exit->total_margin >= 0 ? 'positif' : 'danger'"
                         :hint="$exit->type->generatesRevenue() ? __(':taux % du prix de vente HT', ['taux' => $exit->marginPercent()]) : __('perte sèche')" />
        </div>
    @endif

    {{-- ============ LIGNES + TRAÇABILITÉ DES LOTS ============ --}}
    <div class="space-y-3">
        @foreach ($exit->lines as $ligne)
            @php $prelevements = $mouvements[$ligne->product_id] ?? collect(); @endphp

            <div wire:key="ligne-{{ $ligne->id }}"
                 class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">

                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4 dark:border-slate-800">
                    <div>
                        <p class="font-medium text-slate-900 dark:text-white">
                            {{ $ligne->product?->name ?? __('Produit supprimé') }}
                        </p>
                        <p class="text-xs text-slate-400">{{ $ligne->product?->sku }}</p>
                    </div>

                    <div class="flex flex-wrap gap-5 text-sm">
                        <div class="text-right">
                            <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Quantité') }}</p>
                            <p class="font-medium tabular-nums text-slate-900 dark:text-white">
                                {{ $ligne->product?->unit?->formatQuantity((float) $ligne->quantity)
                                   ?? rtrim(rtrim(\App\Support\Money::number((float) $ligne->quantity, 3), '0'), ',.') }}
                            </p>
                        </div>

                        @if ($voitPrix)
                            <div class="text-right">
                                <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Coût') }}</p>
                                <p class="font-medium tabular-nums text-slate-900 dark:text-white">
                                    {{ Money::format($ligne->cost_total) }}
                                </p>
                            </div>

                            @if ($exit->type->generatesRevenue())
                                <div class="text-right">
                                    <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Vente') }}</p>
                                    <p class="font-medium tabular-nums text-slate-900 dark:text-white">
                                        {{ Money::format($ligne->line_total) }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Marge') }}</p>
                                    <p class="font-medium tabular-nums {{ $ligne->margin >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                        {{ Money::format($ligne->margin) }}
                                    </p>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                @if ($prelevements->isNotEmpty())
                    <div class="bg-slate-50 px-4 py-3 dark:bg-slate-800/40">
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                            {{ __('Lots réellement consommés') }}
                        </p>

                        <div class="space-y-1">
                            @foreach ($prelevements as $mouvement)
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                    <span class="font-mono text-slate-600 dark:text-slate-300">
                                        {{ $mouvement->batch?->batch_number ?? __('hors lot') }}
                                    </span>

                                    <span class="tabular-nums text-slate-700 dark:text-slate-200">
                                        {{ rtrim(rtrim(\App\Support\Money::number((float) $mouvement->quantity, 3), '0'), ',.') }}
                                        {{ $ligne->product?->unit?->symbol }}
                                    </span>

                                    @if ($voitPrix)
                                        <span class="text-slate-400">à {{ Money::format($mouvement->unit_cost) }}</span>
                                        <span class="font-medium tabular-nums text-slate-600 dark:text-slate-300">
                                            = {{ Money::format($mouvement->total_cost) }}
                                        </span>
                                    @endif

                                    @if ($mouvement->batch?->expiry_date)
                                        <span class="text-slate-400">
                                            {{ __('exp.') }} {{ $mouvement->batch->expiry_date->format('d/m/Y') }}
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @if ($exit->note)
        <div class="rounded-2xl border border-slate-200/70 bg-white shadow-doux p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-1 text-sm font-semibold text-slate-900 dark:text-white">{{ __('Note') }}</h2>
            <p class="whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ $exit->note }}</p>
        </div>
    @endif
</div>
