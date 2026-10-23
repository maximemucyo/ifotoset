import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/portfolio.css',
                'resources/js/app.js',
                'resources/js/pages/gallery.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: 'localhost',
        port: 5173,
        strictPort: true,
        cors: true,
    },
    build: {
        cssMinify: 'lightningcss',
        reportCompressedSize: false,
    },
});
