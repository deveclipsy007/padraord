import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'node:path';

export default defineConfig({
    resolve: {
        alias: {
            '@ffmpeg/core-runtime': path.resolve('node_modules/@ffmpeg/core/dist/esm'),
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
    ],
});
