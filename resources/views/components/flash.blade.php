@if (session('succes'))
    <div class="flex items-start gap-2.5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-800 dark:bg-emerald-900/20">
        <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
        <p class="text-sm text-emerald-800 dark:text-emerald-200">{{ __(session('succes')) }}</p>
    </div>
@endif

@if (session('erreur'))
    <div class="flex items-start gap-2.5 rounded-lg border border-rose-200 bg-rose-50 p-3 dark:border-rose-800 dark:bg-rose-900/20">
        <x-icon name="warning" class="mt-0.5 h-4 w-4 shrink-0 text-rose-600 dark:text-rose-400" />
        <p class="text-sm text-rose-800 dark:text-rose-200">{{ __(session('erreur')) }}</p>
    </div>
@endif
