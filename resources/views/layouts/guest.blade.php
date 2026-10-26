<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('naneu.shop.name') }}</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#23804f">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=nunito:600,700,800|nunito-sans:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950">

{{-- Écran d'accès unifié : une seule colonne centrée, le logo de la
     boutique en tête du formulaire, sur tous les formats d'écran. --}}
<main class="relative flex min-h-full flex-col items-center justify-center overflow-hidden px-4 py-10">
    <x-application-logo class="pointer-events-none absolute -right-32 -bottom-32 h-[28rem] w-[28rem] text-marque-400/[0.07]" aria-hidden="true" />

    <x-locale-switch class="absolute right-4 top-4 sm:right-8" />

    <div class="relative w-full max-w-md">
        <div class="rounded-3xl border border-slate-200/70 bg-white px-6 py-8 shadow-doux sm:px-10 sm:py-10 dark:border-slate-800 dark:bg-slate-900">
            <img src="{{ asset('images/logo-naneu.png') }}"
                 alt="{{ config('naneu.shop.name') }} — {{ config('naneu.shop.slogan') }}"
                 class="mx-auto mb-8 w-52 sm:w-60">

            {{ $slot }}
        </div>

        <p class="mt-6 text-center text-xs text-slate-500 dark:text-slate-400">
            {{ __('Accès réservé au personnel. Les comptes sont créés par un administrateur.') }}
        </p>
    </div>
</main>

</body>
</html>
