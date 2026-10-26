{{--
    Bascule clair / sombre. Le choix est mémorisé dans localStorage et
    appliqué avant le premier rendu (voir resources/js/app.js) pour éviter
    le flash blanc au chargement.
--}}
<button x-data
        @click="
            const dark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('naneu-theme', dark ? 'dark' : 'light');
        "
        class="rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
        title="{{ __('Changer de thème') }}"
        aria-label="{{ __('Changer de thème') }}">
    <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>
    </svg>
    <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M12 3v2m0 14v2M5.6 5.6l1.4 1.4m10 10l1.4 1.4M3 12h2m14 0h2M5.6 18.4L7 17m10-10l1.4-1.4M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
    </svg>
</button>
