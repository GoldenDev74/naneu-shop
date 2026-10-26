@props([
    'href',
    'active' => false,
    'icon' => null,
    'badge' => null,
])

<a href="{{ $href }}" wire:navigate
   @class([
       'group flex min-h-10 items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium transition',
       'bg-entete font-semibold text-entete-fonce shadow-sm' => $active,
       'text-slate-600 hover:bg-entete/15 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-entete/20 dark:hover:text-white' => ! $active,
   ])>
    @if ($icon)
        <x-icon :name="$icon" class="h-4.5 w-4.5 shrink-0" />
    @endif

    <span class="flex-1 truncate">{{ $slot }}</span>

    @if ($badge)
        <span class="rounded-md bg-rose-50 px-1.5 py-0.5 text-[11px] font-bold tabular-nums text-rose-600 dark:bg-rose-900/40 dark:text-rose-300">
            {{ $badge > 99 ? '99+' : $badge }}
        </span>
    @endif
</a>
