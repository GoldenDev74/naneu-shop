@use('App\Support\Money')
@use('App\Enums\DocumentStatus')

<div class="space-y-4">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-center gap-4">
            <x-illustration name="inventaires" />
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h1 class="font-mono text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $count->reference }}</h1>
                    <span class="rounded px-2 py-0.5 text-xs font-medium {{ $count->status->badgeClasses() }}">
                        {{ $count->status->label() }}
                    </span>
                </div>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ $count->count_date->translatedFormat('j F Y') }} —
                    {{ $count->scope === 'complet' ? __('inventaire complet') : __('catégorie :nom', ['nom' => $count->category?->name ?? '—']) }}
                </p>
            </div>
        </div>

        <div class="flex gap-2">
            @if ($count->isDraft() && $peutValider)
                <button wire:click="$set('confirmationValidation', true)"
                        class="btn-primaire">
                    <x-icon name="check" class="h-4 w-4" /> {{ __('Valider l\'inventaire') }}
                </button>
            @endif

            <x-back-button :href="route('inventaires.index')">{{ __('Retour') }}</x-back-button>
        </div>
    </div>

    @if ($confirmationValidation)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
            <h2 class="font-semibold text-amber-900 dark:text-amber-200">{{ __('Valider cet inventaire ?') }}</h2>
            <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                {{ $nbEcarts }} {{ __('produit(s) présentent un écart. Leur stock sera ajusté et un mouvement « Écart d\'inventaire » sera inscrit au grand livre pour chacun. Les') }} {{ $nbTotal - $nbComptees }} {{ __('ligne(s) non comptée(s) seront ignorées. Cette validation est définitive.') }}
            </p>

            <div class="mt-3 flex gap-2">
                <button wire:click="valider" wire:loading.attr="disabled"
                        class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="valider">{{ __('Confirmer et ajuster le stock') }}</span>
                    <span wire:loading wire:target="valider">{{ __('Ajustement…') }}</span>
                </button>
                <button wire:click="$set('confirmationValidation', false)"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-white dark:border-slate-600 dark:text-slate-200">
                    {{ __('Revenir') }}
                </button>
            </div>
        </div>
    @endif

    {{-- ============ AVANCEMENT ============ --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat-card :label="__('Produits à compter')" :value="$nbTotal" />
        <x-stat-card :label="__('Comptés')" :value="$nbComptees"
                     :hint="$nbTotal > 0 ? __(':taux % réalisé', ['taux' => round($nbComptees / $nbTotal * 100)]) : null"
                     :tone="$nbComptees === $nbTotal ? 'positif' : 'neutre'" />
        <x-stat-card :label="__('Écarts détectés')" :value="$nbEcarts"
                     :tone="$nbEcarts > 0 ? 'attention' : 'positif'" />
        <x-stat-card :label="__('Valeur des écarts')"
                     :value="$count->status === DocumentStatus::Valide ? Money::format($count->total_variance_value) : '—'" />
    </div>

    {{-- ============ FILTRES ============ --}}
    <div class="rounded-2xl border border-slate-200/70 bg-white shadow-doux p-3 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center gap-3">
            <input type="search" wire:model.live.debounce.300ms="recherche"
                   placeholder="{{ __('Chercher ou scanner un produit…') }}"
                   class="champ min-w-[16rem] flex-1">

            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                <input type="checkbox" wire:model.live="seulementRestantes"
                       class="case">
                {{ __('N\'afficher que les produits restants') }}
            </label>
        </div>
    </div>

    {{-- ============ RAYONS ============ --}}
    <div class="flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="{{ __('Catégories') }}">
        <button type="button" wire:click="choisirCategorie(null)" role="tab" aria-selected="{{ $categorie === null ? 'true' : 'false' }}"
                @class(['shrink-0 rounded-full border px-3.5 py-1.5 text-sm font-medium transition',
                        'border-marque-500 bg-marque-500 text-white' => $categorie === null,
                        'border-slate-200 bg-white text-slate-600 hover:border-marque-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300' => $categorie !== null])>
            {{ __('Tous les rayons') }} <span class="ml-1 tabular-nums opacity-75">{{ $nbComptees }}/{{ $nbTotal }}</span>
        </button>
        @foreach ($rayons as $rayon)
            @php $id = $rayon->id ?? 0; $actif = $categorie === $id; $fini = (int) $rayon->comptes === (int) $rayon->total; @endphp
            <button type="button" wire:key="rayon-{{ $id }}" wire:click="choisirCategorie({{ $id }})" role="tab" aria-selected="{{ $actif ? 'true' : 'false' }}"
                    @class(['inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-medium transition',
                            'border-marque-500 bg-marque-500 text-white' => $actif,
                            'border-slate-200 bg-white text-slate-600 hover:border-marque-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300' => ! $actif])>
                <span class="h-2 w-2 rounded-full" style="background: {{ $rayon->color ?: '#94a3b8' }}"></span>
                {{ $rayon->name ?? __('Sans catégorie') }}
                <span class="tabular-nums opacity-75">{{ $rayon->comptes }}/{{ $rayon->total }}</span>
                @if ($fini) <x-icon name="check" class="h-3.5 w-3.5" /> @endif
                @if ($rayon->ecarts > 0) <span class="rounded-full bg-amber-100 px-1.5 text-[11px] font-semibold text-amber-800">{{ $rayon->ecarts }}</span> @endif
            </button>
        @endforeach
    </div>

    {{-- ============ SAISIE ============ --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-entete">
                    <tr>
                        <x-table.th filter="produit">{{ __('Produit') }}</x-table.th>
                        <x-table.th align="right" filter="theorique" filter-type="number">{{ __('Stock théorique') }}</x-table.th>
                        <x-table.th align="right" filter="compte" filter-type="number">{{ __('Quantité comptée') }}</x-table.th>
                        <x-table.th align="right" filter="ecart" filter-type="number">{{ __('Écart') }}</x-table.th>
                        <x-table.th align="right" filter="valeur" filter-type="number">{{ __('Valeur de l\'écart') }}</x-table.th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @php $rayonCourant = false; $resume = $rayons->keyBy(fn ($r) => $r->id ?? 0); @endphp
                    @foreach ($lignes as $ligne)
                        @if ($rayonCourant !== ($ligne->categorie_id ?? 0))
                            @php $rayonCourant = $ligne->categorie_id ?? 0; $r = $resume[$rayonCourant] ?? null; @endphp
                            <tr wire:key="entete-{{ $rayonCourant }}" class="bg-slate-50/80 dark:bg-slate-800/40">
                                <td colspan="5" class="px-4 py-2">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">
                                            <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $ligne->categorie_couleur ?: '#94a3b8' }}"></span>
                                            {{ $ligne->categorie_nom ?? __('Sans catégorie') }}
                                        </span>
                                        @if ($r)
                                            <span class="text-xs tabular-nums text-slate-500">{{ __(':c / :t comptés', ['c' => $r->comptes, 't' => $r->total]) }}</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                        <tr wire:key="ligne-{{ $ligne->id }}"
                            @class([
                                'hover:bg-slate-50 dark:hover:bg-slate-800/40',
                                'bg-amber-50/50 dark:bg-amber-900/10' => $ligne->hasVariance(),
                            ])>

                            <td class="px-4 py-2.5">
                                <p class="font-medium text-slate-900 dark:text-white">{{ $ligne->product?->name }}</p>
                                <p class="text-xs text-slate-400">{{ $ligne->product?->sku }}</p>
                            </td>

                            <td class="px-4 py-2.5 text-right text-sm tabular-nums text-slate-500 dark:text-slate-400">
                                {{ rtrim(rtrim(\App\Support\Money::number((float) $ligne->expected_quantity, 3), '0'), ',.') }}
                                {{ $ligne->product?->unit?->symbol }}
                            </td>

                            <td class="px-4 py-2.5 text-right">
                                @if ($count->isDraft() && $peutCompter)
                                    <input type="text"
                                           wire:model="saisies.{{ $ligne->id }}"
                                           wire:blur="compter({{ $ligne->id }})"
                                           wire:keydown.enter="compter({{ $ligne->id }})"
                                           inputmode="decimal"
                                           placeholder="—"
                                           class="champ-sm w-28 text-right tabular-nums">

                                    @error("saisies.{$ligne->id}")
                                        <p class="mt-0.5 text-xs text-rose-600">{{ __($message) }}</p>
                                    @enderror
                                @else
                                    <span class="text-sm tabular-nums text-slate-700 dark:text-slate-200">
                                        {{ $ligne->counted_quantity !== null
                                            ? rtrim(rtrim(\App\Support\Money::number((float) $ligne->counted_quantity, 3), '0'), ',.')
                                            : 'non compté' }}
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-2.5 text-right text-sm font-medium tabular-nums">
                                @if ($ligne->isCounted())
                                    <span @class([
                                        'text-emerald-600 dark:text-emerald-400' => (float) $ligne->variance > 0,
                                        'text-rose-600 dark:text-rose-400'       => (float) $ligne->variance < 0,
                                        'text-slate-400'                          => abs((float) $ligne->variance) < 0.0001,
                                    ])>
                                        {{ (float) $ligne->variance > 0 ? '+' : '' }}{{ rtrim(rtrim(\App\Support\Money::number((float) $ligne->variance, 3), '0'), ',.') }}
                                    </span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>

                            <td class="px-4 py-2.5 text-right text-sm tabular-nums text-slate-600 dark:text-slate-300">
                                {{ $ligne->hasVariance() ? Money::format($ligne->variance_value) : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($lignes->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $lignes->links() }}</div>
        @endif
    </div>
</div>
