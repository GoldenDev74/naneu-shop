@props([
    'sortable' => false,
    'column' => null,
    'current' => null,
    'direction' => 'asc',
    'align' => 'left',
    // Filtre de colonne (composant avec WithColumnFilters) : clé dans $filtres.
    'filter' => null,
    // text | select | date | number
    'filterType' => 'text',
    // select : [valeur => libellé]
    'options' => [],
])

@php
    $actif = $sortable && $column === $current;
    $alignement = $align === 'right' ? 'text-right' : ($align === 'center' ? 'text-center' : 'text-left');
    $modele = "filtres.{$filter}";
@endphp

<th scope="col"
    {{ $attributes->except('wire:click')->merge([
        'class' => "px-4 py-3 {$alignement} text-sm font-bold text-entete-fonce",
    ]) }}
    @if ($filter)
        x-data="{
            ouvert: false,
            get filtre() { const v = $wire.get('{{ $modele }}'); return v !== undefined && v !== null && String(v).trim() !== '' },
        }"
    @endif>

    <span class="inline-flex items-center gap-1.5 {{ $align === 'right' ? 'flex-row-reverse' : '' }}">
        <span @if ($sortable) {{ $attributes->only('wire:click') }} class="inline-flex cursor-pointer select-none items-center gap-1 hover:underline" @endif>
            {{ $slot }}

            @if ($sortable)
                <svg class="h-3.5 w-3.5 {{ $actif ? 'text-entete-fonce' : 'text-entete-fonce/40' }}"
                     fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    @if ($actif && $direction === 'desc')
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    @else
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
                    @endif
                </svg>
            @endif
        </span>

        @if ($filter)
            <button type="button" x-ref="bouton" @click="ouvert = ! ouvert"
                    :class="filtre ? 'bg-entete-fonce text-entete' : 'text-entete-fonce/60 hover:bg-white/30 hover:text-entete-fonce'"
                    class="rounded-md p-1 transition"
                    :aria-expanded="ouvert" aria-label="{{ __('Filtrer la colonne') }}" title="{{ __('Filtrer') }}">
                <x-icon name="funnel" class="h-3.5 w-3.5" />
            </button>

            {{-- Hors du tableau (souvent overflow-hidden), positionné sous l'icône. --}}
            @teleport('body')
                <div x-show="ouvert" x-cloak style="display: none"
                     x-anchor.bottom-{{ $align === 'right' ? 'end' : 'start' }}.offset.6="$refs.bouton"
                     @click.outside="if (! $refs.bouton.contains($event.target)) ouvert = false"
                     @keydown.escape.window="ouvert = false"
                     x-transition.opacity
                     class="z-[80] w-64 rounded-xl border border-slate-200 bg-white p-3 text-left font-normal shadow-xl dark:border-slate-700 dark:bg-slate-900">
                    <p class="mb-2 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Filtrer :') }} {{ strip_tags($slot) }}</p>

                    @switch($filterType)
                        @case('select')
                            <select wire:model.live="{{ $modele }}" class="champ-sm w-full">
                                <option value="">{{ __('Tous') }}</option>
                                @foreach ($options as $valeur => $libelle)
                                    <option value="{{ $valeur }}">{{ $libelle }}</option>
                                @endforeach
                            </select>
                            @break

                        @case('date')
                            <input type="date" wire:model.live="{{ $modele }}" class="champ-sm w-full">
                            @break

                        @case('number')
                            <input type="text" wire:model.live.debounce.500ms="{{ $modele }}" inputmode="decimal"
                                   placeholder="{{ __('ex. 10, >10, <5, 10-20') }}" class="champ-sm w-full tabular-nums"
                                   x-init="$watch('ouvert', o => o && $nextTick(() => $el.focus()))">
                            @break

                        @default
                            <input type="search" wire:model.live.debounce.400ms="{{ $modele }}"
                                   placeholder="{{ __('Contient…') }}" class="champ-sm w-full"
                                   x-init="$watch('ouvert', o => o && $nextTick(() => $el.focus()))">
                    @endswitch

                    <div class="mt-2 flex justify-between">
                        <button type="button" x-show="filtre" @click="$wire.set('{{ $modele }}', ''); ouvert = false"
                                class="text-xs font-semibold text-rose-600 hover:underline dark:text-rose-400">{{ __('Effacer') }}</button>
                        <button type="button" @click="ouvert = false"
                                class="ml-auto text-xs font-semibold text-marque-600 hover:underline dark:text-marque-400">{{ __('Fermer') }}</button>
                    </div>
                </div>
            @endteleport
        @endif
    </span>
</th>
