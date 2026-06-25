import { defineConfig } from 'vite'
import path from 'path'

export default defineConfig({
    css: {
        preprocessorOptions: {
            scss: {
                api: 'modern-compiler',
            },
        },
    },
    build: {
        outDir: 'assets',
        emptyOutDir: false,
        rollupOptions: {
            input: {
                app:   path.resolve(__dirname, 'resources/js/app.js'),
                admin: path.resolve(__dirname, 'resources/js/admin.js'),
            },
            output: {
                entryFileNames: 'js/[name].js',
                chunkFileNames: 'js/[name].js',
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name?.endsWith('.css')) {
                        return 'css/[name][extname]'
                    }
                    return 'images/[name][extname]'
                },
            },
        },
        cssMinify: true,
        minify: 'esbuild',
        sourcemap: false,
    },
})
