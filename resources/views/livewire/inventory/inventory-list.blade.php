@use('App\Support\Money')

<div class="space-y-4">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <x-illustration name="inventaires" />
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Inventaires') }}</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Comptage physique et rapprochement avec le stock théorique.') }}
                </p>
            </div>
        </div>

        @if ($peutLancer)
            <button wire:click="$set('panneau', true)"
                    class="btn-primaire">
                <x-icon name="plus" class="h-4 w-4" /> {{ __('Lancer un inventaire') }}
            </button>
        @endif
    </div>

    @if ($panneau && $peutLancer)
        <form wire:submit="lancer"
              class="rounded-xl border border-marque-200 bg-marque-50/40 p-4 dark:border-marque-900 dark:bg-marque-900/10">

            <h2 class="mb-3 font-semibold text-slate-900 dark:text-white">{{ __('Nouvel inventaire') }}</h2>

            <p class="mb-4 text-sm text-slate-600 dark:text-slate-300">
                {{ __('Le stock théorique de chaque produit est figé au moment du lancement. Comptez ensuite en rayon, saisissez les quantités, puis validez : les écarts seront inscrits au grand livre avec le motif « Écart d\'inventaire ».') }}
            </p>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-form.field :label="__('Portée')" name="scope" required>
                    <select wire:model.live="scope"
                            class="champ w-full">
                        <option value="complet">{{ __('Inventaire complet') }}</option>
                        <option value="partiel">{{ __('Une catégorie seulement') }}</option>
                    </select>
                </x-form.field>

                @if ($scope === 'partiel')
                    <x-form.field :label="__('Catégorie')" name="category_id" required>
                        <select wire:model="category_id"
                                class="champ w-full">
                            <option value="">—</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->optionLabel() }}</option>
                            @endforeach
                        </select>
                    </x-form.field>
                @endif

                <x-form.field :label="__('Note')" name="note">
                    <input type="text" wire:model="note" placeholder="{{ __('Inventaire de fin de mois…') }}"
                           class="champ w-full">
                </x-form.field>
            </div>

            <div class="mt-4 flex gap-2">
                <button type="submit"
                        class="btn-primaire">
                    {{ __('Lancer le comptage') }}
                </button>
                <button type="button" wire:click="$set('panneau', false)"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white dark:border-slate-700 dark:text-slate-200">
                    {{ __('Annuler') }}
                </button>
            </div>
        </form>
    @endif

    <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-entete">
                    <tr>
                        <x-table.th filter="reference">{{ __('Référence') }}</x-table.th>
                        <x-table.th filter="date" filter-type="date">{{ __('Date') }}</x-table.th>
                        <x-table.th filter="portee" filter-type="select" :options="['complet' => __('Complet'), 'partiel' => __('Partiel')]">{{ __('Portée') }}</x-table.th>
                        <x-table.th align="right">{{ __('Lignes') }}</x-table.th>
                        <x-table.th align="right" filter="ecart" filter-type="number">{{ __('Écart valorisé') }}</x-table.th>
                        <x-table.th filter="auteur">{{ __('Par') }}</x-table.th>
                        <x-table.th filter="statut" filter-type="select" :options="collect(App\Enums\DocumentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()">{{ __('Statut') }}</x-table.th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($inventaires as $inventaire)
                        <tr wire:key="inv-{{ $inventaire->id }}"
                            class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/40"
                            onclick="window.location='{{ route('inventaires.show', $inventaire) }}'">

                            <td class="px-4 py-3 font-mono text-sm font-medium text-slate-900 dark:text-white">
                                {{ $inventaire->reference }}
                            </td>

                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                {{ $inventaire->count_date->format('d/m/Y') }}
                            </td>

                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                {{ $inventaire->scope === 'complet' ? __('Complet') : ($inventaire->category?->name ?? __('Partiel')) }}
                            </td>

                            <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600 dark:text-slate-300">
                                {{ $inventaire->lines_count }}
                            </td>

                            <td class="px-4 py-3 text-right text-sm font-medium tabular-nums">
                                @if ($inventaire->status->value === 'valide')
                                    <span class="{{ $inventaire->total_variance_value >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                        {{ Money::format($inventaire->total_variance_value) }}
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                                {{ $inventaire->user?->name ?? '—' }}
                            </td>

                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-xs font-medium {{ $inventaire->status->badgeClasses() }}">
                                    {{ $inventaire->status->label() }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center text-sm text-slate-500 dark:text-slate-400">
                                <x-empty-state illustration="inventaires" :message="__('Aucun inventaire réalisé.')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($inventaires->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $inventaires->links() }}</div>
        @endif
    </div>
</div>
