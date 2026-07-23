import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/sass/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        // Bind to all interfaces so the dev server is reachable from outside
        // the container; the browser runs on the Windows host, so HMR and the
        // asset URLs written to public/hot must point at localhost (the mapped
        // port) rather than the container's 0.0.0.0.
        host: '0.0.0.0',
        hmr: {
            host: 'localhost',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
