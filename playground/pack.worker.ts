import { PHP, loadPHPRuntime } from "@php-wasm/universal";
import * as phpLoaderModule from "boxpacker-php-loader";
import manifest from "./php/library-manifest.json";
import { installVolume, volumeFilesFromContents } from "./volume.ts";

export type RotationName = "BestFit" | "KeepFlat" | "Never";

export interface BoxRow {
    reference: string;
    outerWidth: number;
    outerLength: number;
    outerDepth: number;
    emptyWeight: number;
    innerWidth: number;
    innerLength: number;
    innerDepth: number;
    maxWeight: number;
    quantity: number | null;
}

export interface ItemRow {
    description: string;
    width: number;
    length: number;
    depth: number;
    weight: number | null;
    quantity: number;
    rotation: RotationName | null;
}

export interface Problem {
    boxes: BoxRow[];
    items: ItemRow[];
    options: {
        balanceWeight: boolean;
        strictItemOrdering: boolean;
        throwOnUnpackable: boolean;
        timeoutSeconds: number | null;
    };
}

export interface PackResult {
    items: [string, number, number, number, ...number[]][];
    boxes: [
        string,
        number,
        number,
        number,
        [number, number, number, number, number, number, number][],
    ][];
    unpacked: { description: string; quantity: number }[];
    utilisation: number | null;
    error: string | null;
}

interface WorkerRequest {
    id: number;
    warmup?: boolean;
    problem?: Problem;
}

interface WorkerResponse {
    id: number;
    ok: boolean;
    warmed?: boolean;
    result?: PackResult;
    error?: string;
}

const files = manifest as Record<string, string>;
const scriptPath = "/boxpacker/playground/pack.php";
const problemPath = "/boxpacker/problem.json";

let phpPromise: Promise<PHP> | null = null;

async function boot(): Promise<PHP> {
    const runtimeId = await loadPHPRuntime(
        phpLoaderModule as unknown as Parameters<typeof loadPHPRuntime>[0],
    );
    const php = new PHP(runtimeId);
    // Preloaded: this library is small and almost every file is used.
    // The same installer accepts { path, url } entries when a project should
    // fetch a file the first time PHP opens it.
    await installVolume(php, volumeFilesFromContents(files));
    return php;
}

function runtime(): Promise<PHP> {
    if (phpPromise === null) {
        phpPromise = boot().catch((error: unknown) => {
            phpPromise = null;
            throw error;
        });
    }
    return phpPromise;
}

async function replaceRuntime(): Promise<PHP> {
    const current = await phpPromise;
    phpPromise = null;
    const dispose = (Symbol as unknown as { dispose?: symbol }).dispose;
    if (current && dispose) {
        (current as unknown as Record<symbol, () => void>)[dispose]?.();
    }
    return runtime();
}

async function runOnce(php: PHP, problem: Problem): Promise<PackResult> {
    php.writeFile(problemPath, JSON.stringify(problem));
    const response = await php.run({ scriptPath });
    const text = response.text.trim();
    if (text === "") {
        throw new Error(response.errors || "PHP produced no output");
    }
    try {
        return JSON.parse(text) as PackResult;
    } catch {
        const detail = `${response.errors}\n${text}`
            .replace(/<[^>]+>/g, " ")
            .replace(/\s+/g, " ")
            .trim();
        throw new Error(detail.slice(0, 800) || "PHP produced output that was not JSON");
    }
}

async function pack(problem: Problem): Promise<PackResult> {
    let result = await runOnce(await runtime(), problem);
    if (result.error?.includes("Cannot redeclare")) {
        result = await runOnce(await replaceRuntime(), problem);
    }
    return result;
}

const scope = self as unknown as {
    onmessage: (event: MessageEvent<WorkerRequest>) => void;
    postMessage: (message: WorkerResponse) => void;
};

scope.onmessage = (event) => {
    const message = event.data;
    void (async () => {
        try {
            if (message.warmup) {
                await runtime();
                scope.postMessage({ id: message.id, ok: true, warmed: true });
                return;
            }
            if (!message.problem) {
                throw new Error("No packing problem was provided");
            }
            scope.postMessage({
                id: message.id,
                ok: true,
                result: await pack(message.problem),
            });
        } catch (error) {
            phpPromise = null;
            scope.postMessage({
                id: message.id,
                ok: false,
                error: error instanceof Error ? error.message : String(error),
            });
        }
    })();
};
