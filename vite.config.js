import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue'
import { fileURLToPath } from 'node:url';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
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
