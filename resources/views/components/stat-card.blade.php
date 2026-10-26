@props([
    'label',
    'value',
    'hint' => null,
    'icon' => null,
    'tone' => 'neutre',   // neutre | positif | attention | danger
    'href' => null,
    'count' => null,      // valeur brute : active le défilement du chiffre
    'suffix' => '',       // ajouté après le chiffre animé (« produits »)
    'money' => false,     // montant : préfixe/suffixe de la devise courante
    'trend' => null,      // variation en %, affichée en puce ↑ / ↓
    'badge' => null,      // puce de statut libre (texte), colorée selon le ton
])

@php
    // La couleur porte un statut, jamais de la décoration.
    $tones = [
        'neutre'    => 'text-slate-600 bg-slate-100 dark:bg-slate-800 dark:text-slate-300',
        'positif'   => 'text-marque-700 bg-marque-50 dark:bg-marque-900/40 dark:text-marque-300',
        'attention' => 'text-amber-700 bg-amber-50 dark:bg-amber-900/30 dark:text-amber-300',
        'danger'    => 'text-rose-600 bg-rose-50 dark:bg-rose-900/30 dark:text-rose-300',
    ];

    $balise = $href ? 'a' : 'div';

    $affixes = $money
        ? \App\Support\Money::affixes()
        : ['prefix' => '', 'suffix' => $suffix ? ' ' . $suffix : ''];
@endphp

<{{ $balise }}
    @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->merge([
        'class' => 'group block rounded-2xl border border-slate-200/70 bg-white p-5 shadow-doux transition duration-200 dark:border-slate-800 dark:bg-slate-900'
            . ($href ? ' hover:-translate-y-0.5 hover:border-marque-300 dark:hover:border-marque-700' : ''),
    ]) }}>

    <div class="flex items-center gap-2.5">
        @if ($icon)
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $tones[$tone] }}">
                <x-icon :name="$icon" class="h-4 w-4" />
            </span>
        @endif
        <p class="truncate text-[13px] font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
    </div>

    <p class="mt-3 font-display text-[26px] font-bold leading-none tabular-nums text-slate-900 dark:text-white"
       @if ($count !== null) data-count="{{ $count }}" data-prefix="{{ $affixes['prefix'] }}" data-suffix="{{ $affixes['suffix'] }}" @endif>
        {{ $value }}
    </p>

    @if ($trend !== null || $badge || $hint)
        <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
            @if ($trend !== null)
                <span @class([
                    'inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 font-semibold tabular-nums',
                    'bg-marque-50 text-marque-700 dark:bg-marque-900/40 dark:text-marque-300' => $trend >= 0,
                    'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300' => $trend < 0,
                ])>
                    <x-icon :name="$trend >= 0 ? 'arrow-up-right' : 'arrow-down-right'" class="h-3 w-3" />
                    {{ abs($trend) }} %
                </span>
            @endif

            @if ($badge)
                <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 font-semibold {{ $tones[$tone] }}">
                    {{ $badge }}
                </span>
            @endif

            @if ($hint)
                <span class="text-slate-500 dark:text-slate-400">{{ $hint }}</span>
            @endif
        </div>
    @endif
</{{ $balise }}>
