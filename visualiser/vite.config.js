// vite.config.js
// Sphinx loads visualiser.js as a classic <script> (not type="module").
// Vite 8 / Rolldown defaults to ESM + code-splitting, which leaves bare
// `import` statements and breaks in the browser. Force a single IIFE bundle
// so the file runs without module loading (same effective shape as Vite 7).
export default {
    build: {
        outDir: '../docs/_static/js',
        emptyOutDir: true,
        sourcemap: true,
        rolldownOptions: {
            input: [
                'visualiser.ts',
            ],
            // IIFE cannot keep real import.meta; replace it explicitly so Rolldown
            // does not emit EMPTY_IMPORT_META warnings (Vite preload helper, etc.).
            transform: {
                define: {
                    'import.meta': '{}',
                },
            },
            output: {
                entryFileNames: '[name].js',
                // Classic script for Sphinx html_js_files (not type="module")
                format: 'iife',
                name: 'boxpackerVisualiser',
            },
        },
    },
}
