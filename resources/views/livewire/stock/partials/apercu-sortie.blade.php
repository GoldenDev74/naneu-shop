@use('App\Support\Money')

<x-popup close="fermerApercu" :titre="$sortie->reference">
    <x-slot:entete>
        <div class="flex flex-wrap items-center gap-2">
            <h2 class="font-mono text-lg font-bold text-slate-900 dark:text-white">{{ $sortie->reference }}</h2>
            <span class="rounded px-2 py-0.5 text-xs font-medium {{ $sortie->type->badgeClasses() }}">{{ $sortie->type->label() }}</span>
            <span class="rounded px-2 py-0.5 text-xs font-medium {{ $sortie->status->badgeClasses() }}">{{ $sortie->status->label() }}</span>
        </div>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            {{ $sortie->exit_date->translatedFormat('j F Y') }} — {{ __('saisi par') }} {{ $sortie->user?->name ?? '—' }}
        </p>
    </x-slot:entete>

    @if ($voitPrix)
        <dl class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
            @if ($sortie->type->generatesRevenue())
                <div class="rounded-2xl bg-marque-50 px-3 py-2.5 dark:bg-marque-900/20">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-marque-700 dark:text-marque-300">{{ __('Total TTC') }}</dt>
                    <dd class="mt-0.5 font-display text-lg font-bold tabular-nums text-slate-900 dark:text-white">{{ Money::format($sortie->total_sale) }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 px-3 py-2.5 dark:bg-slate-800/50">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Dont TVA') }}</dt>
                    <dd class="mt-0.5 font-display text-lg font-bold tabular-nums text-slate-700 dark:text-slate-200">{{ Money::format($sortie->total_vat) }}</dd>
                </div>
            @endif
            <div class="rounded-2xl bg-slate-50 px-3 py-2.5 dark:bg-slate-800/50">
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Coût d\'achat') }}</dt>
                <dd class="mt-0.5 font-display text-lg font-bold tabular-nums text-slate-700 dark:text-slate-200">{{ Money::format($sortie->total_cost) }}</dd>
            </div>
            <div class="rounded-2xl px-3 py-2.5 {{ $sortie->type->generatesRevenue() && $sortie->total_margin >= 0 ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-rose-50 dark:bg-rose-900/20' }}">
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ $sortie->type->generatesRevenue() ? __('Marge') : __('Perte') }}
                </dt>
                <dd class="mt-0.5 font-display text-lg font-bold tabular-nums {{ $sortie->type->generatesRevenue() && $sortie->total_margin >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }}">
                    {{ $sortie->type->generatesRevenue() ? Money::format($sortie->total_margin) : '−'.Money::format($sortie->total_cost) }}
                </dd>
            </div>
        </dl>
    @endif

    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
        @foreach ($sortie->lines as $ligne)
            <li class="flex items-center gap-3 py-2.5">
                @include('livewire.stock.partials.vignette', ['produit' => $ligne->product])

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $ligne->product?->name ?? __('Produit supprimé') }}</p>
                    <p class="text-xs tabular-nums text-slate-400">
                        {{ $ligne->product?->unit?->formatQuantity((float) $ligne->quantity)
                           ?? rtrim(rtrim(Money::number((float) $ligne->quantity, 3), '0'), ',.') }}
                        @if ($voitPrix && $sortie->type->generatesRevenue() && $ligne->sale_price)
                            × {{ Money::format($ligne->sale_price) }}
                        @endif
                    </p>
                </div>

                @if ($voitPrix)
                    <div class="text-right">
                        <p class="text-sm font-semibold tabular-nums text-slate-900 dark:text-white">
                            {{ Money::format($sortie->type->generatesRevenue() ? $ligne->line_total : $ligne->cost_total) }}
                        </p>
                        @if ($sortie->type->generatesRevenue())
                            <p class="text-xs tabular-nums {{ $ligne->margin >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ __('marge') }} {{ Money::format($ligne->margin) }}
                            </p>
                        @endif
                    </div>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($sortie->note)
        <div class="mt-4 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:bg-slate-800/50 dark:text-slate-300">
            <p class="mb-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Note') }}</p>
            <p class="whitespace-pre-line">{{ $sortie->note }}</p>
        </div>
    @endif

    <x-slot:pied>
        <button type="button" wire:click="fermerApercu" class="btn-secondaire">{{ __('Fermer') }}</button>
        <a href="{{ route('sorties.show', $sortie) }}" wire:navigate class="btn-primaire">
            {{ __('Ouvrir le bon') }} <x-icon name="arrow-right" class="h-4 w-4" />
        </a>
    </x-slot:pied>
</x-popup>
