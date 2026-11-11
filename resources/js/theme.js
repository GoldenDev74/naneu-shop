/**
 * Applique le thème clair / sombre.
 *
 * Ce fichier est importé en tête de app.js, donc exécuté avant que la page
 * ne s'affiche : sans cela, un utilisateur en mode sombre verrait un flash
 * blanc à chaque chargement.
 */
export function appliquerTheme() {
    let stocke = null;

    try {
        stocke = localStorage.getItem('naneu-theme');
    } catch {
        // Stockage indisponible (navigation privée stricte) : on suit le système.
    }

    const systemeSombre = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const sombre = stocke === 'dark' || (stocke === null && systemeSombre);

    document.documentElement.classList.toggle('dark', sombre);
}

appliquerTheme();

// Lors d'une navigation wire:navigate, Livewire recopie les attributs du
// <html> de la nouvelle page (dont class="h-full"), ce qui efface la classe
// « dark ». onSwap s'exécute juste après ce remplacement et avant
// l'affichage : le thème est rétabli sans flash, y compris avec le bouton
// Retour du navigateur.
document.addEventListener('livewire:navigating', (event) => {
    event.detail.onSwap(appliquerTheme);
});
