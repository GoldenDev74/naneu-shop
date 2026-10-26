@props(['label'])

<button type="button" {{ $attributes->merge(['class' => 'inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-marque-50 hover:text-marque-700 dark:hover:bg-marque-900/30 dark:hover:text-marque-300']) }}
        title="{{ $label }}" aria-label="{{ $label }}">
    <x-icon name="eye" class="h-[1.1rem] w-[1.1rem]" />
</button>
