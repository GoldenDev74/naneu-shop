@props([
    'label' => null,
    'name' => null,
    'hint' => null,
    'required' => false,
])

<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <label @if ($name) for="{{ $name }}" @endif
               class="flex items-center gap-1 text-[13px] font-semibold text-slate-700 dark:text-slate-200">
            {{ $label }}
            @if ($required)
                <span class="text-rose-500" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $hint }}</p>
    @endif

    @if ($name)
        @error($name)
            <p class="flex items-center gap-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                <x-icon name="circle-alert" class="h-3.5 w-3.5 shrink-0" />
                {{ __($message) }}
            </p>
        @enderror
    @endif
</div>
