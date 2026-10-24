import path from "node:path";
import { fileURLToPath } from "node:url";
import { defineConfig } from "vite";

const root = path.dirname(fileURLToPath(import.meta.url));

// One ES-module build for the visualiser page. The PHP loader finds php.wasm
// with import.meta.url, and the page imports the canvas from the same graph.
// Loaded only from visualiser.rst. Relative base: the page is served under
// /en/master/ on Read the Docs, so absolute /assets/... URLs would miss _static/js.
export default defineConfig({
    base: "./",
    resolve: {
        alias: {
            "boxpacker-php-loader": path.join(
                root,
                "node_modules/@php-wasm/web-8-5/asyncify/php_8_5.js",
            ),
        },
    },
    build: {
        outDir: "../docs/_static/js",
        emptyOutDir: true,
        sourcemap: true,
        chunkSizeWarningLimit: 25000,
        rolldownOptions: {
            input: path.join(root, "playground.ts"),
            output: {
                entryFileNames: "playground.js",
                chunkFileNames: "playground-assets/[name].js",
                assetFileNames: "playground-assets/[name][extname]",
                format: "es",
            },
        },
    },
    worker: {
        format: "es",
        rolldownOptions: {
            output: {
                entryFileNames: "playground-assets/worker.js",
            },
        },
    },
});
