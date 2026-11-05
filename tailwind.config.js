import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                principal: '#14305D',      // Azul marino
                acento: '#EDB10C',         // Dorado
                grafito: '#5B5B5B',        // Texto principal
                'gris-medio': '#757676',   // Texto secundario
                niebla: '#D8E4F2',         // Azul niebla con más contraste real
            },
        },
    },

    plugins: [forms],
};
