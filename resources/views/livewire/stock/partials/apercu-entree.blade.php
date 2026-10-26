@use('App\Support\Money')

<x-popup close="fermerApercu" :titre="$entree->reference">
    <x-slot:entete>
        <div class="flex flex-wrap items-center gap-2">
            <h2 class="font-mono text-lg font-bold text-slate-900 dark:text-white">{{ $entree->reference }}</h2>
            <span class="rounded px-2 py-0.5 text-xs font-medium {{ $entree->status->badgeClasses() }}">{{ $entree->status->label() }}</span>
        </div>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            {{ $entree->entry_date->translatedFormat('j F Y') }}
            · {{ $entree->supplier?->name ?? __('Sans fournisseur') }}
            @if ($entree->invoice_number) · {{ __('fact.') }} {{ $entree->invoice_number }} @endif
            — {{ __('saisi par') }} {{ $entree->user?->name ?? '—' }}
        </p>
    </x-slot:entete>

    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
        @foreach ($entree->lines as $ligne)
            @php $jours = $ligne->expiry_date ? (int) now()->startOfDay()->diffInDays($ligne->expiry_date, false) : null; @endphp

            <li class="flex items-center gap-3 py-2.5">
                @include('livewire.stock.partials.vignette', ['produit' => $ligne->product])

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $ligne->product?->name ?? __('Produit supprimé') }}</p>
                    <p class="text-xs tabular-nums text-slate-400">
                        {{ $ligne->product?->unit?->formatQuantity((float) $ligne->quantity)
                           ?? rtrim(rtrim(Money::number((float) $ligne->quantity, 3), '0'), ',.') }}
                        × {{ Money::format($ligne->purchase_price) }}
                        @if ($ligne->batch_number) · <span class="font-mono">{{ $ligne->batch_number }}</span> @endif
                    </p>
                </div>

                @if ($ligne->expiry_date)
                    <span @class([
                        'hidden rounded-full px-2 py-0.5 text-xs font-medium sm:inline',
                        'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' => $jours < 0,
                        'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' => $jours >= 0 && $jours <= 7,
                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $jours > 7,
                    ])>{{ __('exp.') }} {{ $ligne->expiry_date->format('d/m/Y') }}</span>
                @endif

                <p class="w-24 text-right text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{{ Money::format($ligne->line_total) }}</p>
            </li>
        @endforeach
    </ul>

    <div class="mt-3 flex items-center justify-between rounded-2xl bg-marque-50 px-4 py-3 dark:bg-marque-900/20">
        <span class="text-sm font-medium text-marque-800 dark:text-marque-200">{{ __('Total du bon') }}</span>
        <span class="font-display text-lg font-bold tabular-nums text-slate-900 dark:text-white">{{ Money::format($entree->total_cost) }}</span>
    </div>

    @if ($entree->note)
        <div class="mt-4 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:bg-slate-800/50 dark:text-slate-300">
            <p class="mb-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Note') }}</p>
            <p class="whitespace-pre-line">{{ $entree->note }}</p>
        </div>
    @endif

    <x-slot:pied>
        <button type="button" wire:click="fermerApercu" class="btn-secondaire">{{ __('Fermer') }}</button>
        <a href="{{ route('entrees.show', $entree) }}" wire:navigate class="btn-primaire">
            {{ __('Ouvrir le bon') }} <x-icon name="arrow-right" class="h-4 w-4" />
        </a>
    </x-slot:pied>
</x-popup>
