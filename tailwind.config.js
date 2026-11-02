import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

const shades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

// The brand palette is driven by CSS variables so administrators can change it from Settings.
const brand = Object.fromEntries(
    shades.map((shade) => [shade, `rgb(var(--brand-${shade}) / <alpha-value>)`]),
);

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                blue: brand,
                indigo: brand,
                brand,
                surface: {
                    base: '#0b1120',
                    raised: '#101827',
                    overlay: '#162033',
                    border: '#1e293b',
                },
            },
            borderRadius: {
                card: '1rem',
                control: '0.75rem',
            },
        },
    },

    plugins: [forms],
};
