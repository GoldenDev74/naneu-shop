{{--
    Fenêtre de consultation pilotée par Livewire : affichée tant que le
    composant parent la rend, fermée par `close` (une action Livewire).
    Échap et clic sur le fond ferment aussi.
--}}
@props(['close', 'titre' => null, 'largeur' => 'max-w-3xl'])

<div x-data="{ destroy() { document.body.classList.remove('overflow-y-hidden') } }"
     x-init="document.body.classList.add('overflow-y-hidden')"
     x-on:keydown.escape.window="$wire.{{ $close }}()"
     class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6"
     role="dialog" aria-modal="true" @if ($titre) aria-label="{{ $titre }}" @endif>

    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-[2px] popup-fond" wire:click="{{ $close }}"></div>

    <div class="popup-carte relative flex max-h-[92vh] w-full {{ $largeur }} flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl dark:bg-slate-900">
        @isset($entete)
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <div class="min-w-0 flex-1">{{ $entete }}</div>
                <button type="button" wire:click="{{ $close }}"
                        class="-mr-1 rounded-full p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        aria-label="{{ __('Fermer') }}">
                    <x-icon name="x" class="h-5 w-5" />
                </button>
            </div>
        @endisset

        <div class="flex-1 overflow-y-auto px-5 py-4">{{ $slot }}</div>

        @isset($pied)
            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-3 dark:border-slate-800 dark:bg-slate-800/40">
                {{ $pied }}
            </div>
        @endisset
    </div>
</div>
