import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'path';

export default defineConfig({
    plugins: [tailwindcss()],
    build: {
        outDir: resolve(import.meta.dirname, 'dist'),
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: {
                app: resolve(import.meta.dirname, 'src/js/app.js'),
                style: resolve(import.meta.dirname, 'src/css/app.css'),
            },
            output: {
                entryFileNames: 'assets/[name].js',
                chunkFileNames: 'assets/[name].js',
                assetFileNames: 'assets/[name].[ext]',
            },
        },
    },
    server: {
        port: 5173,
    },
});
