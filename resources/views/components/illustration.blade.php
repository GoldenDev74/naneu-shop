@props(['name', 'size' => 'md'])

{{--
    Illustration 3D (Microsoft Fluent Emoji, licence MIT — public/images/3d).
    Purement décorative : le titre voisin porte le sens.
--}}
@php
    $tailles = [
        'md' => ['tuile' => 'h-14 w-14 rounded-2xl sm:h-16 sm:w-16', 'image' => 'h-10 w-10 sm:h-11 sm:w-11'],
        'lg' => ['tuile' => 'h-24 w-24 rounded-3xl', 'image' => 'h-16 w-16'],
    ];
    $t = $tailles[$size] ?? $tailles['md'];
@endphp

<span {{ $attributes->merge(['class' => "illustration relative flex shrink-0 items-center justify-center bg-gradient-to-br from-marque-50 via-white to-white shadow-doux ring-1 ring-inset ring-marque-100 dark:from-marque-900/50 dark:via-slate-900 dark:to-slate-900 dark:ring-marque-800/50 {$t['tuile']}"]) }}
      aria-hidden="true">
    <img src="{{ asset("images/3d/{$name}.png") }}" alt="" width="64" height="64" decoding="async"
         class="{{ $t['image'] }} drop-shadow-[0_8px_10px_rgb(22_30_26/0.18)]">
</span>
