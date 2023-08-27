// vite.config.js
export default {
    // config options
    build: {
        outDir: '../docs/_static/js',
        emptyOutDir: true,
        sourcemap: true,
        rollupOptions: {
            input: [
                'visualiser.ts',
            ],
            output: {
                entryFileNames: '[name].js'
            }
        },
    },
}
