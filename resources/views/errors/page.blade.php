{{--
    Modèle commun des pages d'erreur. Volontairement autonome : pas de
    composant Livewire ni d'accès à la base, pour s'afficher même quand
    l'application est en panne (500).

    Variables : $code, $titre, $texte, $icone (nom Lucide), $ton (marque|ambre|rose)
--}}
@php
    $ton = $ton ?? 'marque';
    $pastille = match ($ton) {
        'ambre' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        'rose'  => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
        default => 'bg-marque-100 text-marque-700 dark:bg-marque-900/40 dark:text-marque-300',
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} · {{ $titre }} — {{ config('naneu.shop.name') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#23804f">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=nunito:600,700,800|nunito-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    {{-- Thème sombre sans dépendre du JavaScript de l'application. --}}
    <script>
        try { const t = localStorage.getItem('naneu-theme'); if (t === 'dark' || (t === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}
    </script>
</head>
<body class="h-full bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950">

<main class="relative flex min-h-full flex-col items-center justify-center overflow-hidden px-4 py-10">
    <x-application-logo class="pointer-events-none absolute -right-32 -bottom-32 h-[28rem] w-[28rem] text-marque-400/[0.07]" aria-hidden="true" />

    <div class="relative w-full max-w-md text-center">
        <img src="{{ asset('images/logo-naneu.png') }}" alt="{{ config('naneu.shop.name') }}" class="mx-auto mb-10 w-44">

        <div class="rounded-3xl border border-slate-200/70 bg-white px-6 py-10 shadow-doux sm:px-10 dark:border-slate-800 dark:bg-slate-900">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl {{ $pastille }}">
                <x-icon :name="$icone" class="h-7 w-7" />
            </span>

            <p class="mt-5 font-display text-6xl font-extrabold tracking-tight text-entete">{{ $code }}</p>
            <h1 class="mt-2 font-display text-xl font-extrabold text-slate-900 dark:text-white">{{ $titre }}</h1>
            <p class="mx-auto mt-3 max-w-sm text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $texte }}</p>

            <div class="mt-8 flex flex-col justify-center gap-2 sm:flex-row">
                <button type="button" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')"
                        class="btn-secondaire">
                    <x-icon name="arrow-left" class="h-4 w-4" /> {{ __('Page précédente') }}
                </button>
                {{-- Une adresse inconnue (404) ne charge pas la session : seule la
                     419 propose de se reconnecter ; l'accueil renvoie de lui-même
                     vers la connexion si besoin. --}}
                @if ($code === 419)
                    <a href="{{ route('login') }}" class="btn-primaire">
                        <x-icon name="log-in" class="h-4 w-4" /> {{ __('Se connecter') }}
                    </a>
                @else
                    <a href="{{ route('tableau-de-bord') }}" class="btn-primaire">
                        <x-icon name="home" class="h-4 w-4" /> {{ __('Retour à l\'accueil') }}
                    </a>
                @endif
            </div>
        </div>

        <p class="mt-6 text-xs text-slate-400">{{ config('naneu.shop.name') }} · {{ config('naneu.shop.slogan') }}</p>
    </div>
</main>

</body>
</html>
