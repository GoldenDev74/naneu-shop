/**
 * Applique le thème avant le premier rendu.
 *
 * Ce fichier est importé en tête de app.js, donc exécuté avant que la page
 * ne s'affiche : sans cela, un utilisateur en mode sombre verrait un flash
 * blanc à chaque navigation.
 */
const stocke = localStorage.getItem('naneu-theme');
const systemeSombre = window.matchMedia('(prefers-color-scheme: dark)').matches;

if (stocke === 'dark' || (stocke === null && systemeSombre)) {
    document.documentElement.classList.add('dark');
} else {
    document.documentElement.classList.remove('dark');
}

// Livewire remplace le <body> lors des navigations wire:navigate ;
// la classe posée sur <html> survit, rien à refaire ici.
