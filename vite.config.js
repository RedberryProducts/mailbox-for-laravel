import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    resolve: {
        alias: {
            '@mailbox': fileURLToPath(new URL('./resources', import.meta.url)),
        },
    },
    plugins: [
        vue(),
        laravel({
            hotFile: 'public/vendor/mailbox/mailbox.hot',
            buildDirectory: 'vendor/mailbox',
            input: ['resources/js/dashboard.js'],
            refresh: true,
        }),
    ]
});
