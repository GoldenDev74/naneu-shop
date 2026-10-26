@use('App\Support\Money')

@php
    $r = $resultat;
    $benefice = $r['resultat'] >= 0;
    $maxCharge = max(1, $r['par_categorie']->max('montant'));
    $tauxNet = $r['ventes'] > 0 ? round($r['resultat'] / $r['ventes'] * 100, 1) : null;
@endphp

<div class="space-y-5">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <x-illustration name="rapports" />
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Comptabilité') }}</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Charges de la boutique et bénéfice réellement gagné.') }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button wire:click="ouvrirPostes" class="btn-secondaire">
                <x-icon name="list" class="h-4 w-4" /> {{ __('Postes de charges') }}
            </button>
            <button wire:click="nouvelle" class="btn-primaire">
                <x-icon name="plus" class="h-4 w-4" /> {{ __('Nouvelle charge') }}
            </button>
        </div>
    </div>

    {{-- ============ PÉRIODE ============ --}}
    <div class="rounded-2xl border border-slate-200/70 bg-white shadow-doux p-3 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex flex-wrap gap-1">
                @foreach (['mois' => __('Ce mois'), 'mois_dernier' => __('Mois dernier'), 'trimestre' => __('Ce trimestre'), 'annee' => __('Cette année')] as $cle => $libelle)
                    <button wire:click="periode('{{ $cle }}')"
                            class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        {{ $libelle }}
                    </button>
                @endforeach
            </div>

            <div class="ml-auto flex items-center gap-2">
                <input type="date" wire:model.live="depuis" class="champ-sm" aria-label="{{ __('Du') }}">
                <span class="text-slate-400">→</span>
                <input type="date" wire:model.live="jusqua" class="champ-sm" aria-label="{{ __('Au') }}">
            </div>
        </div>
    </div>

    {{-- ============ COMPTE DE RÉSULTAT ============ --}}
    <div class="grid gap-4 lg:grid-cols-5">

        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-doux lg:col-span-3 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-1 font-semibold text-slate-900 dark:text-white">{{ __('Du chiffre d\'affaires au bénéfice') }}</h2>
            <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
                {{ __('Du :du au :au', ['du' => \Illuminate\Support\Carbon::parse($depuis)->format('d/m/Y'), 'au' => \Illuminate\Support\Carbon::parse($jusqua)->format('d/m/Y')]) }}
            </p>

            <dl class="text-sm">
                <div class="flex items-baseline justify-between py-1.5">
                    <dt class="text-slate-700 dark:text-slate-200">{{ __('Ventes encaissées') }}</dt>
                    <dd class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ Money::format($r['ventes']) }}</dd>
                </div>
                <div class="flex items-baseline justify-between py-1.5 pl-4 text-slate-500 dark:text-slate-400">
                    <dt>− {{ __('Prix d\'achat des produits vendus') }}</dt>
                    <dd class="tabular-nums">{{ Money::format($r['cout_ventes']) }}</dd>
                </div>
                @if ($r['tva'] > 0)
                    <div class="flex items-baseline justify-between py-1.5 pl-4 text-slate-500 dark:text-slate-400">
                        <dt>− {{ __('TVA collectée') }}</dt>
                        <dd class="tabular-nums">{{ Money::format($r['tva']) }}</dd>
                    </div>
                @endif

                <div class="mt-1 flex items-baseline justify-between border-t border-dashed border-slate-200 py-2 dark:border-slate-700">
                    <dt class="font-semibold text-slate-800 dark:text-slate-100">= {{ __('Marge brute') }}</dt>
                    <dd class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ Money::format($r['marge_brute']) }}</dd>
                </div>

                <div class="flex items-baseline justify-between py-1.5 pl-4 text-slate-500 dark:text-slate-400">
                    <dt>
                        − {{ __('Pertes, casse et périmés') }}
                        <span class="text-xs">({{ __('au prix d\'achat') }})</span>
                    </dt>
                    <dd class="tabular-nums {{ $r['pertes'] > 0 ? 'text-amber-600 dark:text-amber-400' : '' }}">{{ Money::format($r['pertes']) }}</dd>
                </div>

                @foreach ($r['par_categorie'] as $poste)
                    <div class="flex items-baseline justify-between py-1.5 pl-4 text-slate-500 dark:text-slate-400" wire:key="cr-{{ $poste['id'] }}">
                        <dt class="flex items-center gap-1.5">
                            − <span class="inline-block h-2 w-2 rounded-full" style="background: {{ $poste['couleur'] }}"></span>
                            {{ $poste['nom'] }}
                        </dt>
                        <dd class="tabular-nums">{{ Money::format($poste['montant']) }}</dd>
                    </div>
                @endforeach

                <div class="mt-2 flex items-center justify-between rounded-xl px-4 py-3 {{ $benefice ? 'bg-marque-50 text-marque-800 dark:bg-marque-900/40 dark:text-marque-200' : 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' }}">
                    <dt class="font-bold">= {{ $benefice ? __('Bénéfice net') : __('Perte nette') }}</dt>
                    <dd class="text-xl font-bold tabular-nums">{{ Money::format($r['resultat']) }}</dd>
                </div>
            </dl>
        </div>

        <div class="space-y-4 lg:col-span-2">
            <div class="grid grid-cols-2 gap-3">
                <x-stat-card :label="__('Charges de la période')" :value="Money::format($r['charges'])" icon="receipt"
                             :tone="$r['charges'] > 0 ? 'attention' : 'neutre'"
                             :hint="$r['marge_brute'] > 0 ? __(':taux % de la marge brute', ['taux' => round($r['charges'] / $r['marge_brute'] * 100, 1)]) : null" />
                <x-stat-card :label="$benefice ? __('Bénéfice net') : __('Perte nette')" :value="Money::format($r['resultat'])" icon="wallet"
                             :tone="$benefice ? 'positif' : 'danger'"
                             :hint="$tauxNet !== null ? __(':taux % des ventes', ['taux' => $tauxNet]) : null" />
            </div>

            <div class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-doux dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-3 flex items-baseline justify-between gap-2">
                    <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Où part l\'argent') }}</h2>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('Cliquez pour filtrer') }}</span>
                </div>

                <ul class="space-y-3">
                    @foreach ($r['par_categorie'] as $poste)
                        <li wire:key="part-{{ $poste['id'] }}">
                            <button type="button" wire:click="filtrerPoste({{ $poste['id'] }})"
                                    class="group w-full rounded-lg p-1.5 text-left transition hover:bg-slate-50 dark:hover:bg-slate-800/60 {{ $categorie === (string) $poste['id'] ? 'bg-slate-50 ring-1 ring-slate-200 dark:bg-slate-800/60 dark:ring-slate-700' : '' }}">
                                <span class="flex items-center justify-between gap-2 text-sm">
                                    <span class="flex min-w-0 items-center gap-2 font-medium text-slate-700 dark:text-slate-200">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white" style="background: {{ $poste['couleur'] }}">
                                            <x-icon :name="$poste['icone']" class="h-4 w-4" />
                                        </span>
                                        <span class="truncate">{{ $poste['nom'] }}</span>
                                    </span>
                                    <span class="shrink-0 tabular-nums text-slate-900 dark:text-white">{{ Money::format($poste['montant']) }}</span>
                                </span>
                                <span class="mt-1.5 block h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <span class="block h-full rounded-full" style="width: {{ round($poste['montant'] / $maxCharge * 100) }}%; background: {{ $poste['couleur'] }}"></span>
                                </span>
                                <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                                    {{ trans_choice(':n paiement|:n paiements', $poste['nombre'], ['n' => $poste['nombre']]) }}
                                    @if ($r['charges'] > 0)
                                        · {{ round($poste['montant'] / $r['charges'] * 100) }} %
                                    @endif
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    {{-- ============ 6 DERNIERS MOIS ============ --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-baseline justify-between gap-2 px-5 pt-4">
            <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Mois par mois') }}</h2>
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('Cliquez sur un mois pour l\'afficher en détail.') }}</span>
        </div>
        <div class="mt-2 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-entete">
                    <tr>
                        <x-table.th>{{ __('Mois') }}</x-table.th>
                        <x-table.th align="right">{{ __('Ventes') }}</x-table.th>
                        <x-table.th align="right">{{ __('Marge brute') }}</x-table.th>
                        <x-table.th align="right">{{ __('Pertes') }}</x-table.th>
                        <x-table.th align="right">{{ __('Charges') }}</x-table.th>
                        <x-table.th align="right">{{ __('Bénéfice net') }}</x-table.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($mensuel->reverse() as $m)
                        @php $vide = $m['ventes'] === 0 && $m['charges'] === 0 && $m['pertes'] === 0; @endphp
                        <tr wire:key="mois-{{ $m['mois'] }}" wire:click="choisirMois('{{ $m['debut'] }}', '{{ $m['fin'] }}')"
                            class="cursor-pointer text-sm hover:bg-slate-50 dark:hover:bg-slate-800/40 {{ $depuis === $m['debut'] ? 'bg-marque-50/60 dark:bg-marque-900/20' : '' }} {{ $vide ? 'text-slate-400' : '' }}">
                            <td class="px-4 py-2.5 font-medium {{ $vide ? '' : 'text-slate-900 dark:text-white' }}">{{ $m['libelle'] }}</td>
                            @if ($vide)
                                <td colspan="5" class="px-4 py-2.5 text-right text-xs">{{ __('Aucune activité') }}</td>
                            @else
                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-600 dark:text-slate-300">{{ Money::format($m['ventes'], false) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-600 dark:text-slate-300">{{ Money::format($m['marge_brute'], false) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-600 dark:text-slate-300">{{ Money::format($m['pertes'], false) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-600 dark:text-slate-300">{{ Money::format($m['charges'], false) }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold tabular-nums {{ $m['resultat'] >= 0 ? 'text-marque-700 dark:text-marque-300' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ Money::format($m['resultat']) }}
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============ JOURNAL DES CHARGES ============ --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center justify-between gap-2 px-5 pt-4">
            <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Charges payées') }}</h2>
            <div class="flex flex-wrap gap-1">
                <button wire:click="filtrerPoste(null)"
                        class="rounded-full px-3 py-1 text-xs font-medium {{ $categorie === '' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' }}">
                    {{ __('Tous les postes') }}
                </button>
                @foreach ($categories as $poste)
                    <button wire:key="pill-{{ $poste->id }}" wire:click="filtrerPoste({{ $poste->id }})"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium {{ $categorie === (string) $poste->id ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' }} {{ $poste->is_active ? '' : 'opacity-60' }}">
                        <span class="h-2 w-2 rounded-full" style="background: {{ $poste->color }}"></span>
                        {{ $poste->name }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-entete">
                    <tr>
                        <x-table.th filter="date" filter-type="date">{{ __('Date') }}</x-table.th>
                        <x-table.th filter="poste" filter-type="select" :options="$categories->pluck('name', 'id')->all()">{{ __('Poste') }}</x-table.th>
                        <x-table.th filter="libelle">{{ __('Libellé') }}</x-table.th>
                        <x-table.th align="right" filter="montant" filter-type="number">{{ __('Montant') }}</x-table.th>
                        <x-table.th filter="auteur">{{ __('Saisi par') }}</x-table.th>
                        <x-table.th align="right">{{ __('Actions') }}</x-table.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($charges as $charge)
                        <tr wire:key="chg-{{ $charge->id }}" class="text-sm hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                                {{ $charge->expense_date->format('d/m/Y') }}
                                <p class="font-mono text-xs text-slate-400">{{ $charge->reference }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium text-white" style="background: {{ $charge->category->color }}">
                                    <x-icon :name="$charge->category->icon" class="h-3 w-3" /> {{ $charge->category->name }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-900 dark:text-white">
                                {{ $charge->label }}
                                @if ($charge->stockEntry)
                                    <a href="{{ route('entrees.show', $charge->stock_entry_id) }}" wire:navigate
                                       class="mt-0.5 flex items-center gap-1 text-xs text-marque-700 hover:underline dark:text-marque-300">
                                        <x-icon name="arrow-down" class="h-3 w-3" /> {{ __('Bon :ref', ['ref' => $charge->stockEntry->reference]) }}
                                    </a>
                                @endif
                                @if ($charge->note)
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $charge->note }}</p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-slate-900 dark:text-white">
                                {{ Money::format($charge->amount) }}
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $charge->user?->name }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <button wire:click="editer({{ $charge->id }})" title="{{ __('Modifier') }}"
                                            class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700">
                                        <x-icon name="pencil" class="h-4 w-4" />
                                    </button>
                                    <button wire:click="supprimer({{ $charge->id }})" title="{{ __('Supprimer') }}"
                                            wire:confirm="{{ __('Supprimer la charge :ref (:montant) ?', ['ref' => $charge->reference, 'montant' => Money::format($charge->amount)]) }}"
                                            class="rounded p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-900/30">
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                                <x-empty-state illustration="rapports" :message="__('Aucune charge sur cette période.')"
                                               :hint="__('Transport, vignettes, facture d\'électricité : enregistrez chaque paiement avec « Nouvelle charge ».')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($charges->total() > 0)
                    <tfoot class="bg-slate-50 dark:bg-slate-800/50">
                        <tr class="text-sm">
                            <td colspan="3" class="px-4 py-2.5 font-medium text-slate-600 dark:text-slate-300">
                                {{ trans_choice(':n charge|:n charges', $charges->total(), ['n' => $charges->total()]) }}
                            </td>
                            <td class="px-4 py-2.5 text-right font-bold tabular-nums text-slate-900 dark:text-white">
                                {{ Money::format($categorie === '' ? $r['charges'] : (int) ($r['par_categorie']->firstWhere('id', (int) $categorie)['montant'] ?? 0)) }}
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if ($charges->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $charges->links() }}</div>
        @endif
    </div>

    {{-- ============ FENÊTRE : SAISIE D'UNE CHARGE ============ --}}
    @if ($formulaire)
        <x-popup close="fermerFormulaire" :titre="$enEdition ? __('Modifier la charge') : __('Nouvelle charge')" largeur="max-w-xl">
            <x-slot:entete>
                <h2 class="font-display text-lg font-bold text-slate-900 dark:text-white">
                    {{ $enEdition ? __('Modifier la charge') : __('Nouvelle charge') }}
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Une dépense de la boutique, hors achat de marchandises.') }}</p>
            </x-slot:entete>

            <form id="form-charge" wire:submit="enregistrer" class="space-y-4">
                <x-form.field :label="__('Poste')" name="expense_category_id" required>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach ($categories->where('is_active', true)->merge($categories->where('id', $expense_category_id))->unique('id') as $poste)
                            <label wire:key="choix-{{ $poste->id }}"
                                   class="flex cursor-pointer items-center gap-2 rounded-xl border p-2.5 text-sm transition {{ $expense_category_id === $poste->id ? 'border-marque-500 bg-marque-50 ring-1 ring-marque-500 dark:bg-marque-900/30' : 'border-slate-200 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800' }}">
                                <input type="radio" wire:model.live="expense_category_id" value="{{ $poste->id }}" class="sr-only">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white" style="background: {{ $poste->color }}">
                                    <x-icon :name="$poste->icon" class="h-4 w-4" />
                                </span>
                                <span class="font-medium leading-tight text-slate-700 dark:text-slate-200">{{ $poste->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </x-form.field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.field :label="__('Montant payé')" name="amount" required>
                        <div class="relative">
                            <input type="text" inputmode="numeric" wire:model="amount" id="amount" placeholder="0"
                                   class="champ w-full pr-16 text-right text-lg font-semibold tabular-nums">
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-slate-400">{{ Money::currency()['symbol'] ?? '' }}</span>
                        </div>
                    </x-form.field>

                    <x-form.field :label="__('Payé le')" name="expense_date" required>
                        <input type="date" wire:model="expense_date" id="expense_date" max="{{ now()->toDateString() }}" class="champ w-full">
                    </x-form.field>
                </div>

                <x-form.field :label="__('Libellé')" name="label" required>
                    <input type="text" wire:model="label" id="label" class="champ w-full"
                           placeholder="{{ __('Ex. : taxi pour la livraison, rouleau de vignettes, facture d\'électricité') }}">
                </x-form.field>

                @if (optional($categories->firstWhere('id', $expense_category_id))->slug === 'transport' || $stock_entry_id)
                    <x-form.field :label="__('Marchandise transportée')" name="stock_entry_id"
                                  :hint="__('Facultatif : rattache ce transport au bon d\'entrée correspondant.')">
                        <select wire:model="stock_entry_id" id="stock_entry_id" class="champ w-full">
                            <option value="">{{ __('— Aucun bon —') }}</option>
                            @foreach ($entrees as $entree)
                                <option value="{{ $entree->id }}">
                                    {{ $entree->reference }} · {{ $entree->entry_date->format('d/m/Y') }}{{ $entree->supplier ? ' · '.$entree->supplier->name : '' }} · {{ Money::format($entree->total_cost) }}
                                </option>
                            @endforeach
                        </select>
                    </x-form.field>
                @endif

                <x-form.field :label="__('Note')" name="note">
                    <textarea wire:model="note" rows="2" class="champ w-full" placeholder="{{ __('N° de reçu, précision…') }}"></textarea>
                </x-form.field>
            </form>

            <x-slot:pied>
                <button type="button" wire:click="fermerFormulaire" class="btn-secondaire">{{ __('Annuler') }}</button>
                <button type="submit" form="form-charge" wire:loading.attr="disabled" class="btn-primaire">
                    {{ $enEdition ? __('Mettre à jour') : __('Enregistrer la charge') }}
                </button>
            </x-slot:pied>
        </x-popup>
    @endif

    {{-- ============ FENÊTRE : POSTES DE CHARGES ============ --}}
    @if ($postes)
        <x-popup close="fermerPostes" :titre="__('Postes de charges')" largeur="max-w-lg">
            <x-slot:entete>
                <h2 class="font-display text-lg font-bold text-slate-900 dark:text-white">{{ __('Postes de charges') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Un poste masqué n\'est plus proposé, ses charges passées restent comptées.') }}</p>
            </x-slot:entete>

            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($categories->sortBy('id') as $poste)
                    <li wire:key="poste-{{ $poste->id }}" class="flex items-center gap-2 py-2.5 {{ $poste->is_active ? '' : 'opacity-60' }}">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-white" style="background: {{ $poste->color }}">
                            <x-icon :name="$poste->icon" class="h-4 w-4" />
                        </span>
                        <input type="text" wire:model="nomsPostes.{{ $poste->id }}" wire:keydown.enter="renommerPoste({{ $poste->id }})"
                               class="champ-sm min-w-0 flex-1" aria-label="{{ __('Nom du poste') }}">
                        <button type="button" wire:click="renommerPoste({{ $poste->id }})" title="{{ __('Renommer') }}"
                                class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700">
                            <x-icon name="check" class="h-4 w-4" />
                        </button>
                        <button type="button" wire:click="basculerPoste({{ $poste->id }})"
                                title="{{ $poste->is_active ? __('Masquer') : __('Réactiver') }}"
                                class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700">
                            <x-icon :name="$poste->is_active ? 'eye-off' : 'eye'" class="h-4 w-4" />
                        </button>
                    </li>
                @endforeach
            </ul>

            <form wire:submit="ajouterPoste" class="mt-4 flex gap-2">
                <input type="text" wire:model="nouveauPoste" placeholder="{{ __('Nouveau poste (ex. : loyer, salaires…)') }}"
                       class="champ min-w-0 flex-1" aria-label="{{ __('Nouveau poste') }}">
                <button type="submit" class="btn-primaire"><x-icon name="plus" class="h-4 w-4" /> {{ __('Ajouter') }}</button>
            </form>
            @error('nouveauPoste') <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
        </x-popup>
    @endif
</div>
