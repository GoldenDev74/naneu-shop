/**
 * Animations de l'interface, pilotées par attributs HTML :
 *
 *   data-reveal      les enfants directs apparaissent en cascade
 *   data-count       le nombre affiché défile depuis zéro
 *   data-bar         la barre de graphique pousse depuis sa base
 *
 * Joué au chargement et après chaque navigation wire:navigate, une seule
 * fois par élément. Rien ne bouge si l'utilisateur a demandé moins de
 * mouvement au système.
 */
import { animate, stagger } from 'motion';

const reduit = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const doux = [0.22, 1, 0.36, 1];

const premiereFois = (el) => {
    if (el.dataset.anime) return false;
    el.dataset.anime = '1';
    return true;
};

// Même séparateur de milliers que Money::format côté serveur.
const grouper = (n) => Math.round(n).toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, document.documentElement.lang === 'en' ? ',' : ' ');

function jouer() {
    if (reduit()) return;

    document.querySelectorAll('[data-reveal]').forEach((groupe) => {
        if (!premiereFois(groupe)) return;
        animate(
            groupe.children,
            { opacity: [0, 1], y: [10, 0] },
            { duration: 0.5, delay: stagger(0.05), ease: doux },
        );
    });

    document.querySelectorAll('[data-count]').forEach((el) => {
        if (!premiereFois(el)) return;
        const cible = Number(el.dataset.count);
        if (!Number.isFinite(cible) || cible === 0) return;
        const prefixe = el.dataset.prefix ?? '';
        const suffixe = el.dataset.suffix ?? '';
        animate(0, cible, {
            duration: 0.9,
            ease: doux,
            onUpdate: (v) => { el.textContent = prefixe + grouper(v) + suffixe; },
        });
    });

    const barres = [...document.querySelectorAll('[data-bar]')].filter(premiereFois);
    if (barres.length) {
        animate(
            barres,
            { scaleY: [0, 1] },
            { duration: 0.6, delay: stagger(0.03), ease: doux },
        );
    }
}

document.addEventListener('DOMContentLoaded', jouer);
document.addEventListener('livewire:navigated', jouer);
