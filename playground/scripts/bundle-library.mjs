// Stamp src/ and the playground runner into one manifest the WASM worker writes
// onto the virtual filesystem before PHP starts. PSR-3 comes from vendor/psr/log,
// installed by Composer (Read the Docs runs composer install --no-dev before this
// script). A tree that is too large to preload uses scripts/publish-volume.mjs
// and installVolume() url entries instead of this manifest.
import { mkdir, readdir, readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const playgroundDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const repoRoot = path.resolve(playgroundDir, "..");

async function phpFiles(dir) {
    const entries = await readdir(dir, { withFileTypes: true });
    const files = [];
    for (const entry of entries) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            files.push(...(await phpFiles(full)));
        } else if (entry.name.endsWith(".php")) {
            files.push(full);
        }
    }
    return files;
}

function vfsPath(prefix, root, file) {
    const relative = path.relative(root, file).split(path.sep).join("/");
    return `${prefix}/${relative}`;
}

const files = {};

for (const file of await phpFiles(path.join(repoRoot, "src"))) {
    files[vfsPath("/boxpacker/src", path.join(repoRoot, "src"), file)] = await readFile(file, "utf8");
}

const psrLogSrc = path.join(repoRoot, "vendor", "psr", "log", "src");
let psrLogFiles;
try {
    psrLogFiles = await phpFiles(psrLogSrc);
} catch (error) {
    if (error && error.code === "ENOENT") {
        throw new Error("vendor/psr/log is missing. Run composer install before building the playground.");
    }
    throw error;
}
if (psrLogFiles.length === 0) {
    throw new Error("vendor/psr/log/src has no PHP files. Run composer install before building the playground.");
}
for (const file of psrLogFiles) {
    files[vfsPath("/boxpacker/psr-log", psrLogSrc, file)] = await readFile(file, "utf8");
}

for (const name of ["pack.php", "PlaygroundBox.php", "PlaygroundItem.php"]) {
    const file = path.join(playgroundDir, "php", name);
    files[`/boxpacker/playground/${name}`] = await readFile(file, "utf8");
}

files["/boxpacker/autoload.php"] = `<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'DVDoug\\\\BoxPacker\\\\' => __DIR__ . '/src/',
        'Psr\\\\Log\\\\' => __DIR__ . '/psr-log/',
    ];
    foreach ($prefixes as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $path = $dir . str_replace('\\\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) {
                require $path;
            }

            return;
        }
    }
});
`;

const destination = path.join(playgroundDir, "php", "library-manifest.json");
await mkdir(path.dirname(destination), { recursive: true });
await writeFile(destination, JSON.stringify(files));
console.log(`Wrote ${Object.keys(files).length} PHP files to ${destination}`);
