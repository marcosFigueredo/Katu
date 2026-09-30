import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
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
        },
    },

    corePlugins: {
        // Desativado: a utilitária .collapse do Tailwind (visibility: collapse)
        // colide com a classe .collapse do componente de accordion do Bootstrap,
        // tornando o conteúdo expandido invisível mesmo com o espaço reservado.
        visibility: false,
    },

    plugins: [forms],
};
