import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue'; // <--- 1. Importa esto
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(), 
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js', 
        },
    },
});
