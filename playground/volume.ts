// Virtual filesystem installer shared by playground workers.
//
// A file is either already in memory (`contents`) or a static URL (`url`).
// URL entries become Emscripten lazy-file nodes: the name is visible to PHP
// immediately, and the bytes are fetched the first time PHP stats or reads
// that path. Bytes already read stay in the worker for later reads.
//
// URL files have to be installed in a worker. The fetch is a synchronous
// XHR, so the URL must be same-origin http(s). Serve the file without
// Content-Encoding. HEAD should return 200 with Content-Length.
// Accept-Ranges: bytes makes a read pull 1 MiB chunks; otherwise the first
// stat downloads the whole file. stat, is_file, file_exists, and filesize
// all trigger that length request, so walking a directory and statting
// every child requests every child. A path that was not installed is
// absent and produces no request. A failed fetch aborts the runtime.
//
// php.mount replays the installer after a runtime hot swap. Unmount unlinks
// the planted names first, so the swap does not read every lazy file while
// it copies the filesystem.

import type { PHP, UnmountFunction } from "@php-wasm/universal";

export const volumeIndexVersion = 1;

export interface PreloadedVolumeFile {
    path: string;
    contents: string | Uint8Array;
}

export interface LazyVolumeFile {
    path: string;
    url: string;
}

export type VolumeFile = PreloadedVolumeFile | LazyVolumeFile;

export interface VolumeIndexFile {
    path: string;
    url: string;
}

export interface VolumeIndex {
    version: typeof volumeIndexVersion;
    files: VolumeIndexFile[];
}

let nextMountId = 0;

export function readVolumeIndex(value: unknown): VolumeIndex {
    if (typeof value !== "object" || value === null) {
        throw new Error("Volume index must be an object");
    }
    const version = Reflect.get(value, "version");
    if (version !== volumeIndexVersion) {
        throw new Error(`Volume index version must be ${volumeIndexVersion}`);
    }
    const files = Reflect.get(value, "files");
    if (!Array.isArray(files)) {
        throw new Error("Volume index files must be an array");
    }
    const entries: VolumeIndexFile[] = [];
    for (const entry of files) {
        if (typeof entry !== "object" || entry === null) {
            throw new Error("Volume index entry must be an object");
        }
        const filePath = Reflect.get(entry, "path");
        const url = Reflect.get(entry, "url");
        if (typeof filePath !== "string" || typeof url !== "string") {
            throw new Error("Volume index entry needs a string path and url");
        }
        entries.push({ path: filePath, url });
    }
    return { version: volumeIndexVersion, files: entries };
}

// `indexUrl` is the absolute URL the index was fetched from. Each entry URL
// is resolved against that, the same way a relative link in the index file works.
export function volumeFilesFromIndex(index: VolumeIndex, indexUrl: string | URL): LazyVolumeFile[] {
    const base = typeof indexUrl === "string" ? indexUrl : indexUrl.href;
    return index.files.map((file) => ({
        path: file.path,
        url: new URL(file.url, base).href,
    }));
}

export function volumeFilesFromContents(
    files: Readonly<Record<string, string | Uint8Array>>,
): PreloadedVolumeFile[] {
    return Object.entries(files).map(([path, contents]) => ({ path, contents }));
}

export async function installVolume(php: PHP, files: readonly VolumeFile[]): Promise<UnmountFunction> {
    const preloaded: PreloadedVolumeFile[] = [];
    const lazy: LazyVolumeFile[] = [];
    for (const file of files) {
        assertAbsolutePath(file.path);
        if ("contents" in file && "url" in file) {
            throw new Error(`Volume file needs contents or a url: ${file.path}`);
        }
        if ("contents" in file) {
            if (typeof file.contents !== "string" && !(file.contents instanceof Uint8Array)) {
                throw new Error(`Volume file contents must be a string or bytes: ${file.path}`);
            }
            preloaded.push({ path: file.path, contents: file.contents });
            continue;
        }
        if ("url" in file) {
            assertHttpUrl(file.url);
            lazy.push({ path: file.path, url: file.url });
            continue;
        }
        const unnamed = file as { path?: string };
        throw new Error(`Volume file needs contents or a url: ${unnamed.path ?? "(missing path)"}`);
    }

    const paths = [...preloaded, ...lazy].map((file) => file.path);
    assertDisjoint(paths);
    if (lazy.length > 0) {
        assertLazyFilesRunInWorker();
    }

    const mountPoint = `/internal/volumes/${++nextMountId}`;
    for (const filePath of paths) {
        if (
            filePath === mountPoint
            || filePath.startsWith(`${mountPoint}/`)
            || mountPoint.startsWith(`${filePath}/`)
        ) {
            throw new Error(`Volume path overlaps the installer mount point ${mountPoint}: ${filePath}`);
        }
    }

    const directories = directoriesFor(paths);
    return php.mount(mountPoint, (mounted, FS) => {
        const planted: string[] = [];
        const removePlanted = (): void => {
            for (const filePath of planted) {
                try {
                    FS.unlink(filePath);
                } catch {
                    // Already gone, or this filesystem has been replaced.
                }
            }
        };
        try {
            mounted.mkdir(mountPoint);
            for (const dir of directories) {
                mounted.mkdir(dir);
            }
            for (const file of preloaded) {
                mounted.writeFile(file.path, file.contents);
                planted.push(file.path);
            }
            for (const file of lazy) {
                const place = splitPath(file.path);
                FS.createLazyFile(place.parent, place.name, file.url, true, false);
                planted.push(file.path);
            }
        } catch (error) {
            removePlanted();
            throw error;
        }
        return removePlanted;
    });
}

function assertLazyFilesRunInWorker(): void {
    if (typeof (globalThis as { importScripts?: unknown }).importScripts !== "function") {
        throw new Error("Lazy volume files are fetched with synchronous XHR, which only works in a worker.");
    }
}

function assertAbsolutePath(filePath: string): void {
    if (!filePath.startsWith("/") || filePath.includes("\\") || filePath.includes("\0")) {
        throw new Error(`Volume path must be an absolute file path: ${filePath}`);
    }
    const segments = filePath.split("/");
    if (segments.length < 2) {
        throw new Error(`Volume path must be an absolute file path: ${filePath}`);
    }
    for (let index = 1; index < segments.length; index += 1) {
        const segment = segments[index];
        if (segment === "" || segment === "." || segment === "..") {
            throw new Error(`Volume path must be an absolute file path: ${filePath}`);
        }
    }
}

function assertHttpUrl(url: string): void {
    let parsed: URL;
    try {
        parsed = new URL(url);
    } catch {
        throw new Error(`Volume file URL must be absolute http(s): ${url}`);
    }
    if (parsed.protocol !== "http:" && parsed.protocol !== "https:") {
        throw new Error(`Volume file URL must be http(s): ${url}`);
    }
}

function assertDisjoint(paths: string[]): void {
    const seen = new Set<string>();
    for (const filePath of paths) {
        if (seen.has(filePath)) {
            throw new Error(`Volume path is listed twice: ${filePath}`);
        }
        seen.add(filePath);
    }
    for (const filePath of paths) {
        const segments = filePath.split("/");
        let directory = "";
        for (let index = 1; index < segments.length - 1; index += 1) {
            directory += `/${segments[index]}`;
            if (seen.has(directory)) {
                throw new Error(`Volume path is both a file and a directory: ${directory}`);
            }
        }
    }
}

function directoriesFor(paths: string[]): string[] {
    const dirs = new Set<string>();
    for (const filePath of paths) {
        const parts = filePath.split("/");
        parts.pop();
        let acc = "";
        for (const part of parts) {
            if (part === "") {
                continue;
            }
            acc += `/${part}`;
            dirs.add(acc);
        }
    }
    return [...dirs].sort((left, right) => left.length - right.length);
}

function splitPath(filePath: string): { parent: string; name: string } {
    const slash = filePath.lastIndexOf("/");
    return {
        parent: slash <= 0 ? "/" : filePath.slice(0, slash),
        name: filePath.slice(slash + 1),
    };
}
