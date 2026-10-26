@props(['title' => null])

<div>
    @if ($title)
        <p class="px-3 pb-1 eyebrow text-slate-500 dark:text-slate-400">
            {{ $title }}
        </p>
    @endif

    <div class="space-y-0.5">
        {{ $slot }}
    </div>
</div>
