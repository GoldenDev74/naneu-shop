import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/*
 * Charte Naneu Shop — dérivée du logo.
 *
 * marque : le vert du monogramme (#56C389 = marque-400). Les nuances 600+
 *          servent au texte et aux boutons : marque-600 sur blanc = 4,9:1 (AA).
 * neutres : gris légèrement teintés de vert, pour que fonds, bordures et
 *          texte appartiennent à la même famille que la marque. Ils
 *          remplacent slate et gray afin que toutes les vues existantes
 *          adoptent la charte sans réécriture.
 */
const marque = {
    50: '#effaf3',
    100: '#d9f2e3',
    200: '#b5e5ca',
    300: '#86d3a9',
    400: '#56c389',
    500: '#36a66c',
    600: '#23804f',
    700: '#1d6a42',
    800: '#1b5638',
    900: '#17472f',
    950: '#0b2a1b',
};

const neutres = {
    50: '#f6f8f6',
    100: '#edf1ee',
    200: '#dce3de',
    300: '#c0cbc4',
    400: '#7d8c83',
    500: '#5f6e65',
    600: '#4a5850',
    700: '#38443d',
    800: '#232d28',
    900: '#161e1a',
    950: '#0c120f',
};

/** @type {import('tailwindcss').Config} */
export default {
    // Bascule pilotée par une classe sur <html>, posée par resources/js/theme.js.
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
        './app/Enums/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Nunito : terminaisons arrondies, écho du lettrage du logo.
                display: ['Nunito', ...defaultTheme.fontFamily.sans],
                sans: ['"Nunito Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                marque,
                // En-têtes de tableaux et menu actif. Texte foncé dessus :
                // #0b2a1b sur #55BF81 = 6,7:1 (le blanc n'atteindrait que 2,3:1).
                entete: {
                    DEFAULT: '#55BF81',
                    fonce: '#0b2a1b',
                },
                slate: neutres,
                gray: neutres,
                // Les composants Breeze utilisent indigo pour le focus.
                indigo: marque,
            },
            boxShadow: {
                // Ombre diffuse teintée vert : cartes posées, sans contour dur.
                doux: '0 1px 2px rgb(22 30 26 / 0.04), 0 12px 32px -16px rgb(22 30 26 / 0.12)',
            },
            letterSpacing: {
                // Espacement du slogan sous le logo.
                slogan: '0.32em',
            },
            spacing: {
                '4.5': '1.125rem',
            },
        },
    },

    plugins: [forms],
};
