import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/rappasoft/laravel-livewire-tables/resources/views/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/Livewire/**/*.php',
        './node_modules/flowbite/**/*.js',
    ],

    darkMode: 'class',

    theme: {
        extend: {
            fontFamily: {
                sans: [
                    'Figtree',
                    ...defaultTheme.fontFamily.sans,
                ],
            },
            colors: {
                primary: {
                    DEFAULT: '#7C3AED',
                    600: '#7C3AED',
                    500: '#9F7CFF'
                },
                accent: '#A855F7',
                neutral: {
                    900: '#111827',
                    800: '#1f2937',
                    700: '#374151'
                },
                emerald: {
                    DEFAULT: '#10B981'
                },
                danger: {
                    DEFAULT: '#EF4444'
                },
                shadow: '#4C1D95'
            },
        },
    },

    plugins: [
        forms,
        require('daisyui'),
        require('flowbite/plugin'),
    ],
    daisyui: {
        themes: [
            'dark',
            'cupcake',
        ],
    },
};