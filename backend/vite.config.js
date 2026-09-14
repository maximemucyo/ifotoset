import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/pages/gallery.js',
                'resources/js/pages/photographer.js',
                'resources/js/pages/studio-gallery.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
