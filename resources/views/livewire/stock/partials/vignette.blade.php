@php $image = $produit?->imageUrl(); @endphp

@if ($image)
    <img src="{{ $image }}" alt="" loading="lazy" class="h-10 w-10 shrink-0 rounded-xl object-cover ring-1 ring-slate-200/70 dark:ring-slate-700">
@else
    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-marque-50 font-display text-sm font-bold text-marque-700 dark:bg-marque-900/30 dark:text-marque-300">
        {{ mb_strtoupper(mb_substr($produit?->name ?? '?', 0, 1)) }}
    </span>
@endif
