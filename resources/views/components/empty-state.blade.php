@props(['illustration', 'message', 'hint' => null])

{{-- État vide : une illustration, une phrase, et l'action utile en slot. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center text-center']) }}>
    <x-illustration :name="$illustration" size="lg" />
    <p class="mt-4 font-display text-base font-bold text-slate-800 dark:text-slate-100">{{ $message }}</p>
    @if ($hint)
        <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">{{ $hint }}</p>
    @endif
    {{ $slot }}
</div>
