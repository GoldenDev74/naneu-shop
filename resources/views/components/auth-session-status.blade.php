@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-800 dark:bg-emerald-900/20']) }}>
        <p class="text-sm text-emerald-800 dark:text-emerald-200">{{ $status }}</p>
    </div>
@endif
