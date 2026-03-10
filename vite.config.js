import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/zpl-preview.js'],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                // Preserve ES module exports (needed for dynamic import of zpl-preview)
                format: 'es',
            },
        },
    },
    server: {
        host: '127.0.0.1', // Force IPv4 instead of IPv6
        cors: true,
    },
});
