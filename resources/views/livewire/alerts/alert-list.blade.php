<div class="space-y-4">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <x-illustration name="alertes" />
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Alertes') }}</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Stock bas, ruptures et péremptions — mises à jour en temps réel.') }}
                </p>
            </div>
        </div>

        <div class="flex gap-2">
            <button wire:click="reanalyser" wire:loading.attr="disabled"
                    class="btn-secondaire">
                <x-icon name="refresh" class="h-4 w-4" />
                <span wire:loading.remove wire:target="reanalyser">{{ __('Relancer l\'analyse') }}</span>
                <span wire:loading wire:target="reanalyser">{{ __('Analyse…') }}</span>
            </button>

            @if ($nbNonTraitees > 0 && $peutTraiter)
                <button wire:click="acquitterTout"
                        class="btn-primaire">
                    <x-icon name="check" class="h-4 w-4" /> {{ __('Tout marquer traité') }}
                </button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-3 gap-3">
        <x-stat-card :label="__('Alertes ouvertes')" :value="$nbOuvertes" icon="bell"
                     :tone="$nbOuvertes > 0 ? 'attention' : 'positif'" />
        <x-stat-card :label="__('Critiques')" :value="$nbCritiques" icon="warning"
                     :tone="$nbCritiques > 0 ? 'danger' : 'positif'" />
        <x-stat-card :label="__('Non traitées')" :value="$nbNonTraitees" icon="clock" />
    </div>

    <div class="rounded-2xl border border-slate-200/70 bg-white shadow-doux p-3 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap gap-2">
            <select wire:model.live="etat"
                    class="champ">
                <option value="ouvertes">{{ __('Alertes ouvertes') }}</option>
                <option value="non_traitees">{{ __('Non traitées') }}</option>
                <option value="resolues">{{ __('Résolues') }}</option>
                <option value="toutes">{{ __('Toutes') }}</option>
            </select>

            <select wire:model.live="type"
                    class="champ">
                <option value="">{{ __('Tous les types') }}</option>
                @foreach ($types as $valeur => $libelle)
                    <option value="{{ $valeur }}">{{ $libelle }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="space-y-2">
        @forelse ($alertes as $alerte)
            <div wire:key="alerte-{{ $alerte->id }}"
                 @class([
                     'flex flex-wrap items-start gap-3 rounded-xl border bg-white p-4 dark:bg-slate-900',
                     'border-rose-200 dark:border-rose-900' => $alerte->severity->value === 'critique' && $alerte->isOpen(),
                     'border-amber-200 dark:border-amber-900' => $alerte->severity->value === 'avertissement' && $alerte->isOpen(),
                     'border-slate-200 dark:border-slate-800' => ! $alerte->isOpen() || $alerte->severity->value === 'info',
                     'opacity-60' => ! $alerte->isOpen(),
                 ])>

                <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $alerte->severity->dotClasses() }}"></span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded px-2 py-0.5 text-xs font-medium {{ $alerte->severity->badgeClasses() }}">
                            {{ $alerte->type->label() }}
                        </span>

                        @if (! $alerte->isOpen())
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                {{ __('résolue') }} {{ $alerte->resolved_at->diffForHumans() }}
                            </span>
                        @elseif ($alerte->isAcknowledged())
                            <span class="rounded bg-sky-100 px-2 py-0.5 text-xs text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">
                                {{ __('traitée par') }} {{ $alerte->acknowledgedBy?->name }}
                            </span>
                        @endif
                    </div>

                    <p class="mt-1.5 text-sm text-slate-800 dark:text-slate-100">{{ $alerte->message }}</p>

                    <p class="mt-1 text-xs text-slate-400">
                        {{ __('Déclenchée') }} {{ $alerte->triggered_at?->diffForHumans() }}
                        @if ($alerte->batch) · {{ __('lot') }} {{ $alerte->batch->batch_number }} @endif
                    </p>
                </div>

                <div class="flex shrink-0 gap-2">
                    @if ($alerte->product_id && auth()->user()->canAccess('lots'))
                        <a href="{{ route('produits.lots', $alerte->product_id) }}" wire:navigate
                           class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                            {{ __('Voir les lots') }}
                        </a>
                    @endif

                    @if ($alerte->isOpen() && ! $alerte->isAcknowledged() && $peutTraiter)
                        <button wire:click="acquitter({{ $alerte->id }})"
                                class="rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-slate-700 dark:bg-slate-700 dark:hover:bg-slate-600">
                            {{ __('Marquer traité') }}
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 py-16 text-center dark:border-emerald-900 dark:bg-emerald-900/20">
                <x-icon name="check" class="mx-auto h-8 w-8 text-emerald-500" />
                <p class="mt-2 font-medium text-emerald-800 dark:text-emerald-200">{{ __('Aucune alerte.') }}</p>
                <p class="text-sm text-emerald-600 dark:text-emerald-400">
                    {{ __('Le stock et les dates de péremption sont sous contrôle.') }}
                </p>
            </div>
        @endforelse
    </div>

    @if ($alertes->hasPages())
        <div>{{ $alertes->links() }}</div>
    @endif
</div>
