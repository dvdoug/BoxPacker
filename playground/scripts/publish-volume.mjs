// Copy source trees into a directory of static files and write volume-index.json
// beside them. A worker fetches that index and passes volumeFilesFromIndex()
// to installVolume() in ../volume.ts. Entry URLs are relative to the index
// file. BoxPacker's library stays in library-manifest.json and is preloaded;
// this script is the publisher for a tree that is too large to preload
// (thousands of JSON files, or hundreds of megabytes of binaries).
//
//   node scripts/publish-volume.mjs --dest <output-directory> \
//       [--index volume-index.json] --map /vfs/prefix=<host-directory> [--map ...]
//
// Serve --dest without Content-Encoding on these files. HEAD must return 200
// and Content-Length. Accept-Ranges: bytes lets PHP read a file in 1 MiB chunks.

import { copyFile, mkdir, readdir, stat, writeFile } from "node:fs/promises";
import path from "node:path";

const args = parseArgs(process.argv.slice(2));
const dest = path.resolve(args.dest);
const maps = args.maps.map(parseMap);
for (const entry of maps) {
    if (overlaps(dest, entry.host) || overlaps(entry.host, dest)) {
        throw new Error(`Volume destination overlaps the source tree: ${entry.host}`);
    }
}

const indexPath = path.resolve(dest, args.indexName);
const indexDir = path.dirname(indexPath);
if (path.relative(dest, indexDir).startsWith("..") || path.isAbsolute(path.relative(dest, indexDir))) {
    throw new Error(`Index path must stay inside ${dest}`);
}
const files = [];
const seen = new Set();
for (const entry of maps) {
    let sourceStat;
    try {
        sourceStat = await stat(entry.host);
    } catch (error) {
        if (error && error.code === "ENOENT") {
            throw new Error(`Volume source does not exist: ${entry.host}`);
        }
        throw error;
    }
    if (!sourceStat.isDirectory()) {
        throw new Error(`Volume source is not a directory: ${entry.host}`);
    }
    for (const file of await filesIn(entry.host)) {
        const relative = path.relative(entry.host, file).split(path.sep).join("/");
        if (relative === "" || relative.startsWith("../") || path.isAbsolute(relative)) {
            throw new Error(`File escaped ${entry.host}: ${file}`);
        }
        const vfsPath = `${entry.prefix}/${relative}`;
        if (seen.has(vfsPath)) {
            throw new Error(`Volume path is listed twice: ${vfsPath}`);
        }
        seen.add(vfsPath);
        const diskRelative = vfsPath.replace(/^\/+/, "");
        const target = path.join(dest, ...diskRelative.split("/"));
        const url = path.relative(indexDir, target).split(path.sep).join("/");
        files.push({ path: vfsPath, url, source: file, target });
    }
}

if (files.length === 0) {
    throw new Error("Volume source trees contain no files");
}

files.sort((left, right) => (left.path < right.path ? -1 : left.path > right.path ? 1 : 0));

for (const file of files) {
    await mkdir(path.dirname(file.target), { recursive: true });
    await copyFile(file.source, file.target);
}

if (indexDir !== dest) {
    await mkdir(indexDir, { recursive: true });
}
const index = {
    // Keep in step with volumeIndexVersion in volume.ts.
    version: 1,
    files: files.map((file) => ({ path: file.path, url: file.url })),
};
await writeFile(indexPath, JSON.stringify(index));
console.log(`Wrote ${files.length} volume files and ${indexPath}`);

function parseArgs(argv) {
    let destArg = "";
    let indexName = "volume-index.json";
    const maps = [];
    for (let index = 0; index < argv.length; index += 1) {
        const arg = argv[index];
        if (arg === "--dest") {
            destArg = required(argv, index, arg);
            index += 1;
        } else if (arg === "--index") {
            indexName = required(argv, index, arg);
            index += 1;
        } else if (arg === "--map") {
            maps.push(required(argv, index, arg));
            index += 1;
        } else {
            throw new Error(`Unknown argument ${arg}\n${usage()}`);
        }
    }
    if (destArg === "" || maps.length === 0) {
        throw new Error(usage());
    }
    return { dest: destArg, indexName, maps };
}

function required(argv, index, flag) {
    const value = argv[index + 1];
    if (value === undefined || value.startsWith("--")) {
        throw new Error(`${flag} needs a value\n${usage()}`);
    }
    return value;
}

function usage() {
    return "Usage: node scripts/publish-volume.mjs --dest <output-directory> [--index volume-index.json] --map /vfs/prefix=<host-directory> [--map ...]";
}

function parseMap(spec) {
    const eq = spec.indexOf("=");
    if (eq <= 0) {
        throw new Error(`Expected /vfs/prefix=<host-directory>, got ${spec}`);
    }
    let prefix = spec.slice(0, eq).replaceAll("\\", "/").replace(/\/+$/, "");
    if (!prefix.startsWith("/")) {
        throw new Error(`VFS prefix must be absolute: ${prefix}`);
    }
    const segments = prefix.split("/");
    if (segments.slice(1).some((segment) => segment === "" || segment === "." || segment === "..")) {
        throw new Error(`VFS prefix must be an absolute directory: ${prefix}`);
    }
    return { prefix, host: path.resolve(spec.slice(eq + 1)) };
}

async function filesIn(dir) {
    const entries = await readdir(dir, { withFileTypes: true });
    const files = [];
    for (const entry of entries) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            files.push(...(await filesIn(full)));
        } else if (entry.isFile() || entry.isSymbolicLink()) {
            files.push(full);
        }
    }
    return files;
}

function overlaps(parent, child) {
    const relative = path.relative(path.resolve(parent), path.resolve(child));
    return relative === "" || (!relative.startsWith("..") && !path.isAbsolute(relative));
}
