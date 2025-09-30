import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue'
import { fileURLToPath } from 'node:url';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        vue(),
        laravel({
            hotFile: 'public/vendor/mailbox/mailbox.hot', // Most important lines
            buildDirectory: 'vendor/mailbox', // Most important lines
            input: ['resources/css/mailbox.css', 'resources/js/mailbox.js'],
            refresh: true,
        }),
    ]
});
