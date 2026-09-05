import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    server: {
        host: "inventoraisdkdemovue.test",
        port: 5173,
        hmr: {
            host: "inventoraisdkdemovue.test",
        },
        watch: {
            usePolling: true,
            ignored: [
                '**/node_modules/**',
                '**/.git/**',
                '**/vendor/**',
                '**/storage/**',
            ],
        },
    },
    plugins: [
        tailwindcss(),
        laravel({
            input: 'resources/js/app.ts',
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
