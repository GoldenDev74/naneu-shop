@props(['title' => null, 'description' => null, 'icon' => null])

{{-- Section de formulaire : en-tête (icône, titre, aide) séparé du contenu. --}}
<section {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900']) }}>
    @if ($title)
        <header class="flex items-start gap-3.5 border-b border-slate-100 px-5 py-4 sm:px-6 dark:border-slate-800">
            @if ($icon)
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-marque-50 text-marque-700 ring-1 ring-inset ring-marque-100 dark:bg-marque-900/40 dark:text-marque-300 dark:ring-marque-800/60">
                    <x-icon :name="$icon" class="h-5 w-5" />
                </span>
            @endif
            <div class="min-w-0 pt-0.5">
                <h2 class="font-display text-base font-bold text-slate-900 dark:text-white">{{ $title }}</h2>
                @if ($description)
                    <p class="mt-0.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $description }}</p>
                @endif
            </div>
        </header>
    @endif

    <div class="px-5 py-5 sm:px-6">
        {{ $slot }}
    </div>
</section>
