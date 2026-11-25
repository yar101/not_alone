import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class",
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
                'admin-base': 'rgb(var(--admin-bg-base-rgb) / <alpha-value>)',
                'admin-surface': 'rgb(var(--admin-bg-surface-rgb) / <alpha-value>)',
                'admin-card': 'rgb(var(--admin-bg-card-rgb) / <alpha-value>)',
                'admin-primary': 'rgb(var(--admin-primary-rgb) / <alpha-value>)',
                'admin-primary-hover': 'rgb(var(--admin-primary-hover-rgb) / <alpha-value>)',
                'admin-accent': 'rgb(var(--admin-text-accent-rgb) / <alpha-value>)',
            },
        },
    },

    plugins: [forms],
};
