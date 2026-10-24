import type {
    BoxRow,
    ItemRow,
    PackResult,
    Problem,
    RotationName,
} from "./pack.worker.ts";
import { showPacking as drawCanvas } from "./visualiser/visualiser.ts";

interface PackingData {
    // description, width, length, depth, then weight and rotation when the link has them
    items: [string, number, number, number, ...number[]][];
    boxes: [
        string,
        number,
        number,
        number,
        [number, number, number, number, number, number, number][],
    ][];
}

const PREFILL_NOTE =
    "This picture is a packing that was already done. The tables are not the boxes and items you started with, because a link like this does not remember everything. Where a detail was missing, we have filled in a guess. Each box's outer size is copied from the inside, the empty weight is 0, and the maximum weight is 1000000 g so it rarely rules a box out. Quantity is how many of that box were used, so Pack will not reach for any more. The options are this page's usual choices, and they may not be yours: balance weight on, keep item order off, do not fail when an item fits nowhere, and a timeout of 15 seconds. A blank weight or rotation means an older link, and Pack will wait until you fill it in.";

const REPACK_NOTE =
    "This picture is the packing you just ran. It may not match the one you opened.";

const COPY_HINT_WAITING = "To copy a link, first press Pack.";
const COPY_TITLE_WAITING = "Press Pack yourself first.";
const COPY_TITLE_READY = "Copy a link to the packing you just ran.";

let copyReady = false;
let editGeneration = 0;

const worker = new Worker(new URL("./pack.worker.ts", import.meta.url), {
    type: "module",
});

let requestId = 0;
const pending = new Map<
    number,
    {
        resolve: (result: PackResult) => void;
        reject: (error: Error) => void;
    }
>();

worker.onmessage = (
    event: MessageEvent<{
        id: number;
        ok: boolean;
        warmed?: boolean;
        result?: PackResult;
        error?: string;
    }>,
) => {
    const waiting = pending.get(event.data.id);
    if (!waiting) {
        return;
    }
    pending.delete(event.data.id);
    if (event.data.warmed) {
        waiting.resolve({
            items: [],
            boxes: [],
            unpacked: [],
            utilisation: null,
            error: null,
        });
        return;
    }
    if (!event.data.ok || !event.data.result) {
        waiting.reject(new Error(event.data.error || "Packing failed"));
        return;
    }
    waiting.resolve(event.data.result);
};

worker.onerror = (event) => {
    for (const waiting of pending.values()) {
        waiting.reject(new Error(event.message || "The packing worker failed"));
    }
    pending.clear();
};

function askWorker(message: { warmup?: boolean; problem?: Problem }): Promise<PackResult> {
    const id = ++requestId;
    return new Promise((resolve, reject) => {
        pending.set(id, { resolve, reject });
        worker.postMessage({ id, ...message });
    });
}

function defaultOptions(): Problem["options"] {
    return {
        balanceWeight: true,
        strictItemOrdering: false,
        throwOnUnpackable: false,
        timeoutSeconds: 15,
    };
}

function defaultProblem(): Problem {
    // Each bar is 100×50×50 mm. The small carton holds 2, the medium cube
    // holds 4, and the large carton holds 6. Eight bars therefore pack as a
    // full large carton plus a full small one. Weight balancing moves them
    // into two full medium cartons instead.
    return {
        boxes: [
            {
                reference: "Small",
                outerWidth: 110,
                outerLength: 110,
                outerDepth: 60,
                emptyWeight: 30,
                innerWidth: 100,
                innerLength: 100,
                innerDepth: 50,
                maxWeight: 20000,
                quantity: null,
            },
            {
                reference: "Medium",
                outerWidth: 110,
                outerLength: 110,
                outerDepth: 110,
                emptyWeight: 50,
                innerWidth: 100,
                innerLength: 100,
                innerDepth: 100,
                maxWeight: 20000,
                quantity: null,
            },
            {
                reference: "Large",
                outerWidth: 160,
                outerLength: 110,
                outerDepth: 110,
                emptyWeight: 70,
                innerWidth: 150,
                innerLength: 100,
                innerDepth: 100,
                maxWeight: 20000,
                quantity: null,
            },
        ],
        items: [
            {
                description: "Candle",
                width: 100,
                length: 50,
                depth: 50,
                weight: 500,
                quantity: 4,
                rotation: "BestFit",
            },
            {
                description: "Soap",
                width: 100,
                length: 50,
                depth: 50,
                weight: 500,
                quantity: 4,
                rotation: "BestFit",
            },
        ],
        options: defaultOptions(),
    };
}

function blankBox(): BoxRow {
    return {
        reference: "Box",
        outerWidth: 100,
        outerLength: 100,
        outerDepth: 100,
        emptyWeight: 0,
        innerWidth: 100,
        innerLength: 100,
        innerDepth: 100,
        maxWeight: 1000,
        quantity: null,
    };
}

function blankItem(): ItemRow {
    return {
        description: "Item",
        width: 10,
        length: 10,
        depth: 10,
        weight: 1,
        quantity: 1,
        rotation: "BestFit",
    };
}

function storedInteger(value: unknown): number | null {
    return typeof value === "number" && Number.isInteger(value) ? value : null;
}

function rotationFromStored(value: unknown): RotationName | null {
    if (value === 1) {
        return "Never";
    }
    if (value === 2) {
        return "KeepFlat";
    }
    if (value === 6) {
        return "BestFit";
    }
    return null;
}

function problemFromPacking(packing: PackingData): Problem {
    const items = packing.items.map((item, index) => {
        let quantity = 0;
        for (const box of packing.boxes) {
            for (const placed of box[4]) {
                if (placed[0] === index) {
                    quantity += 1;
                }
            }
        }
        return {
            description: String(item[0]),
            width: Number(item[1]),
            length: Number(item[2]),
            depth: Number(item[3]),
            weight: item.length >= 5 ? storedInteger(item[4]) : null,
            quantity: Math.max(1, quantity),
            rotation: item.length >= 6 ? rotationFromStored(item[5]) : null,
        };
    });

    const groups = new Map<string, BoxRow>();
    for (const box of packing.boxes) {
        const key = JSON.stringify([box[0], box[1], box[2], box[3]]);
        const existing = groups.get(key);
        if (existing && existing.quantity !== null) {
            existing.quantity += 1;
            continue;
        }
        groups.set(key, {
            reference: String(box[0]),
            outerWidth: Number(box[1]),
            outerLength: Number(box[2]),
            outerDepth: Number(box[3]),
            emptyWeight: 0,
            innerWidth: Number(box[1]),
            innerLength: Number(box[2]),
            innerDepth: Number(box[3]),
            maxWeight: 1000000,
            quantity: 1,
        });
    }

    return { boxes: [...groups.values()], items, options: defaultOptions() };
}

function normaliseProblem(value: unknown): Problem {
    if (!value || typeof value !== "object") {
        throw new Error("Problem must be an object");
    }
    const raw = value as {
        boxes?: BoxRow[];
        items?: ItemRow[];
        options?: Partial<Problem["options"]>;
    };
    if (!Array.isArray(raw.boxes) || !Array.isArray(raw.items)) {
        throw new Error("Problem is missing tables");
    }
    const rotation = (name: string): RotationName =>
        name === "KeepFlat" || name === "Never" || name === "BestFit" ? name : "BestFit";
    return {
        boxes: raw.boxes.map((box) => ({
            reference: String(box.reference ?? ""),
            outerWidth: Number(box.outerWidth),
            outerLength: Number(box.outerLength),
            outerDepth: Number(box.outerDepth),
            emptyWeight: Number(box.emptyWeight),
            innerWidth: Number(box.innerWidth),
            innerLength: Number(box.innerLength),
            innerDepth: Number(box.innerDepth),
            maxWeight: Number(box.maxWeight),
            quantity:
                box.quantity === null || box.quantity === undefined
                    ? null
                    : Number(box.quantity),
        })),
        items: raw.items.map((item) => ({
            description: String(item.description ?? ""),
            width: Number(item.width),
            length: Number(item.length),
            depth: Number(item.depth),
            weight: Number(item.weight),
            quantity: Number(item.quantity),
            rotation: rotation(String(item.rotation)),
        })),
        options: {
            balanceWeight: raw.options?.balanceWeight !== false,
            strictItemOrdering: raw.options?.strictItemOrdering === true,
            throwOnUnpackable: raw.options?.throwOnUnpackable === true,
            timeoutSeconds:
                raw.options && "timeoutSeconds" in raw.options
                    ? raw.options.timeoutSeconds === null
                        ? null
                        : Number(raw.options.timeoutSeconds)
                    : 15,
        },
    };
}

function hashParams(): URLSearchParams {
    const hash = window.location.hash.startsWith("#")
        ? window.location.hash.slice(1)
        : window.location.hash;
    return new URLSearchParams(hash);
}

function readInitial(): { problem: Problem; packing: PackingData | null; note: string } {
    const problemRaw = hashParams().get("problem");
    if (problemRaw !== null) {
        try {
            return {
                problem: normaliseProblem(JSON.parse(problemRaw)),
                packing: null,
                note: "",
            };
        } catch {
            return {
                problem: defaultProblem(),
                packing: null,
                note: "That problem link could not be read. Showing the demo instead.",
            };
        }
    }

    const packingRaw =
        hashParams().get("packing") ??
        new URLSearchParams(window.location.search).get("packing");
    if (packingRaw !== null) {
        try {
            const packing = JSON.parse(packingRaw) as PackingData;
            return {
                problem: problemFromPacking(packing),
                packing,
                note: PREFILL_NOTE,
            };
        } catch {
            return {
                problem: defaultProblem(),
                packing: null,
                note: "That visualiser link could not be read. Showing the demo instead.",
            };
        }
    }

    return { problem: defaultProblem(), packing: null, note: "" };
}

function problemHref(problem: Problem): string {
    const url = new URL(window.location.href);
    url.search = "";
    url.hash = "problem=" + encodeURIComponent(JSON.stringify(problem));
    return url.toString();
}

function escapeHtml(value: string): string {
    return value.replace(/[&<>"']/g, (character) => {
        switch (character) {
            case "&":
                return "&amp;";
            case "<":
                return "&lt;";
            case ">":
                return "&gt;";
            case '"':
                return "&quot;";
            default:
                return "&#39;";
        }
    });
}

function numberField(name: string, value: number | null): string {
    return `<input type="number" step="1" name="${name}" value="${value === null ? "" : value}">`;
}

function boxRowHtml(box: BoxRow): string {
    const quantity = box.quantity === null ? "" : String(box.quantity);
    return `<tr>
        <td><input type="text" name="reference" value="${escapeHtml(box.reference)}"></td>
        <td>${numberField("outerWidth", box.outerWidth)}</td>
        <td>${numberField("outerLength", box.outerLength)}</td>
        <td>${numberField("outerDepth", box.outerDepth)}</td>
        <td>${numberField("emptyWeight", box.emptyWeight)}</td>
        <td>${numberField("innerWidth", box.innerWidth)}</td>
        <td>${numberField("innerLength", box.innerLength)}</td>
        <td>${numberField("innerDepth", box.innerDepth)}</td>
        <td>${numberField("maxWeight", box.maxWeight)}</td>
        <td><input type="number" step="1" min="1" name="quantity" value="${quantity}" placeholder="∞" title="Blank means unlimited"></td>
        <td><button type="button" data-remove-box>Remove</button></td>
    </tr>`;
}

function itemRowHtml(item: ItemRow): string {
    const option = (name: RotationName, label: string) =>
        `<option value="${name}"${item.rotation === name ? " selected" : ""}>${label}</option>`;
    const unknown =
        item.rotation === null ? `<option value="" selected></option>` : "";
    return `<tr>
        <td><input type="text" name="description" value="${escapeHtml(item.description)}"></td>
        <td>${numberField("width", item.width)}</td>
        <td>${numberField("length", item.length)}</td>
        <td>${numberField("depth", item.depth)}</td>
        <td>${numberField("weight", item.weight)}</td>
        <td>${numberField("quantity", item.quantity)}</td>
        <td><select name="rotation">${unknown}${option("BestFit", "Best fit")}${option("KeepFlat", "Keep flat")}${option("Never", "No rotation")}</select></td>
        <td><button type="button" data-remove-item>Remove</button></td>
    </tr>`;
}

function render(root: HTMLElement, problem: Problem): void {
    const options = problem.options;
    root.innerHTML = `
        <style>
            #playground .bp-scroll { overflow-x: auto; margin: 0.4rem 0 1rem; }
            #playground input[type="number"] { width: 5.5rem; }
            #playground input[type="text"] { width: 9rem; }
            #playground .bp-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; margin: 0.75rem 0; }
            #playground .bp-options { display: flex; flex-wrap: wrap; gap: 0.75rem 1.25rem; margin: 0.5rem 0; }
            #playground .bp-note, #playground .bp-hint, #playground .bp-status { margin: 0.4rem 0; }
            #playground .bp-note:empty, #playground .bp-hint:empty { display: none; }
            #playground button:disabled { opacity: 0.55; cursor: not-allowed; }
        </style>
        <p class="bp-note" id="bp-note"></p>
        <h3>Boxes</h3>
        <div class="bp-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Reference</th><th>Outer W</th><th>Outer L</th><th>Outer D</th><th>Empty g</th>
                        <th>Inner W</th><th>Inner L</th><th>Inner D</th><th>Max g</th><th>Qty</th><th></th>
                    </tr>
                </thead>
                <tbody id="bp-boxes">${problem.boxes.map(boxRowHtml).join("")}</tbody>
            </table>
        </div>
        <p><button type="button" id="bp-add-box">Add box</button></p>
        <h3>Items</h3>
        <div class="bp-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Description</th><th>W</th><th>L</th><th>D</th><th>Weight g</th><th>Qty</th><th>Rotation</th><th></th>
                    </tr>
                </thead>
                <tbody id="bp-items">${problem.items.map(itemRowHtml).join("")}</tbody>
            </table>
        </div>
        <p><button type="button" id="bp-add-item">Add item</button></p>
        <div class="bp-options">
            <label><input type="checkbox" id="bp-balance"${options.balanceWeight ? " checked" : ""}> Balance weight</label>
            <label><input type="checkbox" id="bp-strict"${options.strictItemOrdering ? " checked" : ""}> Keep item order</label>
            <label><input type="checkbox" id="bp-throw"${options.throwOnUnpackable ? " checked" : ""}> Fail if an item fits nowhere</label>
            <label>Timeout (seconds) <input type="number" id="bp-timeout" min="0" step="1" value="${options.timeoutSeconds ?? ""}" placeholder="none" title="Blank means no timeout"></label>
        </div>
        <div class="bp-actions">
            <button type="button" id="bp-pack">Pack</button>
            <button type="button" id="bp-copy" disabled title="${COPY_TITLE_WAITING}">Copy reproducer link to clipboard</button>
        </div>
        <p class="bp-hint" id="bp-copy-hint">${COPY_HINT_WAITING}</p>
        <p class="bp-status" id="bp-status" role="status"></p>
    `;

    root.querySelector("#bp-add-box")?.addEventListener("click", () => {
        try {
            const next = readForm({ allowIncomplete: true });
            next.boxes.push(blankBox());
            const note = noteText();
            markEdited();
            render(root, next);
            setNote(note);
        } catch (error) {
            setStatus(error instanceof FormError ? error.message : "The tables could not be read.");
        }
    });
    root.querySelector("#bp-add-item")?.addEventListener("click", () => {
        try {
            const next = readForm({ allowIncomplete: true });
            next.items.push(blankItem());
            const note = noteText();
            markEdited();
            render(root, next);
            setNote(note);
        } catch (error) {
            setStatus(error instanceof FormError ? error.message : "The tables could not be read.");
        }
    });
    root.querySelectorAll("[data-remove-box]").forEach((button) => {
        button.addEventListener("click", () => {
            const row = button.closest("tr");
            row?.remove();
            markEdited();
        });
    });
    root.querySelectorAll("[data-remove-item]").forEach((button) => {
        button.addEventListener("click", () => {
            button.closest("tr")?.remove();
            markEdited();
        });
    });
    setCopyReady(copyReady);
    root.querySelector("#bp-pack")?.addEventListener("click", () => {
        void runPack(true);
    });
    root.querySelector("#bp-copy")?.addEventListener("click", () => {
        void copyLink();
    });
}

function field(row: HTMLTableRowElement, name: string): HTMLInputElement {
    const input = row.querySelector(`[name="${name}"]`);
    if (!(input instanceof HTMLInputElement)) {
        throw new Error(`Missing ${name}`);
    }
    return input;
}

function readInteger(
    input: HTMLInputElement,
    label: string,
    errors: string[],
    minimum: number,
): number | null {
    const raw = input.value.trim();
    if (!/^-?\d+$/.test(raw)) {
        errors.push(`${label} must be an integer`);
        return null;
    }
    const value = Number(raw);
    if (value < minimum) {
        errors.push(`${label} must be at least ${minimum}`);
        return null;
    }
    return value;
}

function readForm(options?: { allowIncomplete?: boolean }): Problem {
    const allowIncomplete = options?.allowIncomplete === true;
    const errors: string[] = [];
    const boxes = [...document.querySelectorAll<HTMLTableRowElement>("#bp-boxes tr")].map(
        (row, index) => {
            const label = `Box ${index + 1}`;
            const reference = field(row, "reference").value.trim();
            if (reference === "") {
                errors.push(`${label} needs a reference`);
            }
            const quantityRaw = field(row, "quantity").value.trim();
            let quantity: number | null = null;
            if (quantityRaw !== "") {
                quantity = readInteger(field(row, "quantity"), `${label} quantity`, errors, 1);
            }
            return {
                reference,
                outerWidth: readInteger(field(row, "outerWidth"), `${label} outer width`, errors, 1) ?? 0,
                outerLength: readInteger(field(row, "outerLength"), `${label} outer length`, errors, 1) ?? 0,
                outerDepth: readInteger(field(row, "outerDepth"), `${label} outer depth`, errors, 1) ?? 0,
                emptyWeight: readInteger(field(row, "emptyWeight"), `${label} empty weight`, errors, 0) ?? 0,
                innerWidth: readInteger(field(row, "innerWidth"), `${label} inner width`, errors, 1) ?? 0,
                innerLength: readInteger(field(row, "innerLength"), `${label} inner length`, errors, 1) ?? 0,
                innerDepth: readInteger(field(row, "innerDepth"), `${label} inner depth`, errors, 1) ?? 0,
                maxWeight: readInteger(field(row, "maxWeight"), `${label} max weight`, errors, 0) ?? 0,
                quantity,
            };
        },
    );
    const items = [...document.querySelectorAll<HTMLTableRowElement>("#bp-items tr")].map(
        (row, index) => {
            const label = `Item ${index + 1}`;
            const description = field(row, "description").value.trim();
            if (description === "") {
                errors.push(`${label} needs a description`);
            }
            const weightInput = field(row, "weight");
            let weight: number | null;
            if (weightInput.value.trim() === "") {
                weight = null;
                if (!allowIncomplete) {
                    errors.push(`${label} needs a weight`);
                }
            } else {
                weight = readInteger(weightInput, `${label} weight`, errors, 0);
            }
            const rotationElement = row.querySelector('[name="rotation"]');
            const rotationValue =
                rotationElement instanceof HTMLSelectElement ? rotationElement.value : "";
            let rotation: RotationName | null = null;
            if (
                rotationValue === "BestFit" ||
                rotationValue === "KeepFlat" ||
                rotationValue === "Never"
            ) {
                rotation = rotationValue;
            } else if (!allowIncomplete) {
                errors.push(`${label} needs a rotation`);
            }
            return {
                description,
                width: readInteger(field(row, "width"), `${label} width`, errors, 1) ?? 0,
                length: readInteger(field(row, "length"), `${label} length`, errors, 1) ?? 0,
                depth: readInteger(field(row, "depth"), `${label} depth`, errors, 1) ?? 0,
                weight,
                quantity: readInteger(field(row, "quantity"), `${label} quantity`, errors, 1) ?? 0,
                rotation,
            } satisfies ItemRow;
        },
    );

    const timeoutInput = document.getElementById("bp-timeout");
    let timeoutSeconds: number | null = null;
    if (timeoutInput instanceof HTMLInputElement && timeoutInput.value.trim() !== "") {
        timeoutSeconds = readInteger(timeoutInput, "Timeout", errors, 0);
    }

    const problem: Problem = {
        boxes,
        items,
        options: {
            balanceWeight: checked("bp-balance"),
            strictItemOrdering: checked("bp-strict"),
            throwOnUnpackable: checked("bp-throw"),
            timeoutSeconds,
        },
    };
    if (errors.length > 0) {
        throw new FormError(errors);
    }
    return problem;
}

class FormError extends Error {
    constructor(readonly messages: string[]) {
        super(messages.map((message) => (message.endsWith(".") ? message : `${message}.`)).join(" "));
    }
}

function checked(id: string): boolean {
    const input = document.getElementById(id);
    return input instanceof HTMLInputElement && input.checked;
}

function statusElement(): HTMLElement | null {
    return document.getElementById("bp-status");
}

function setStatus(message: string): void {
    const status = statusElement();
    if (status) {
        status.textContent = message;
    }
}

function noteText(): string {
    return document.getElementById("bp-note")?.textContent ?? "";
}

function setNote(message: string): void {
    const note = document.getElementById("bp-note");
    if (note) {
        note.textContent = message;
    }
}

function setCopyReady(ready: boolean): void {
    copyReady = ready;
    const button = document.getElementById("bp-copy");
    if (button instanceof HTMLButtonElement) {
        button.disabled = !ready;
        button.title = ready ? COPY_TITLE_READY : COPY_TITLE_WAITING;
    }
    const hint = document.getElementById("bp-copy-hint");
    if (hint) {
        hint.textContent = ready ? "" : COPY_HINT_WAITING;
    }
}

function markEdited(): void {
    editGeneration += 1;
    setCopyReady(false);
}

function summary(result: PackResult): string {
    const boxCount = result.boxes.length;
    const boxWord = boxCount === 1 ? "box" : "boxes";
    const parts = [`Packed into ${boxCount} ${boxWord}.`];
    if (result.utilisation !== null) {
        parts.push(`Volume utilisation ${result.utilisation}%.`);
    }
    if (result.unpacked.length > 0) {
        const unpacked = result.unpacked
            .map((item) => `${item.quantity} × ${item.description}`)
            .join(", ");
        parts.push(`Not packed: ${unpacked}.`);
    }
    return parts.join(" ");
}

function draw(packing: PackingData): void {
    drawCanvas(packing);
}

function showPacking(result: PackResult): void {
    draw({ items: result.items, boxes: result.boxes });
}

async function runPack(updateUrl: boolean): Promise<void> {
    let problem: Problem;
    try {
        problem = readForm();
    } catch (error) {
        if (updateUrl) {
            setCopyReady(false);
        }
        setStatus(error instanceof FormError ? error.message : "The tables could not be read.");
        return;
    }
    if (problem.boxes.length === 0 || problem.items.length === 0) {
        if (updateUrl) {
            setCopyReady(false);
        }
        setStatus("Add at least one box and one item.");
        return;
    }

    if (updateUrl) {
        setCopyReady(false);
    }
    const packedGeneration = editGeneration;
    const packButton = document.getElementById("bp-pack");
    if (packButton instanceof HTMLButtonElement) {
        packButton.disabled = true;
    }
    setStatus("Packing…");
    try {
        const result = await askWorker({ problem });
        if (result.error) {
            setStatus(result.error);
            return;
        }
        showPacking(result);
        setStatus(summary(result));
        if (updateUrl && packedGeneration === editGeneration) {
            if (noteText() === PREFILL_NOTE) {
                setNote(REPACK_NOTE);
            }
            history.replaceState(null, "", problemHref(problem));
            setCopyReady(true);
            document.getElementById("visualiser-picture")?.scrollIntoView({
                behavior: "smooth",
                block: "start",
            });
        }
    } catch (error) {
        setStatus(error instanceof Error ? error.message : "Packing failed");
    } finally {
        if (packButton instanceof HTMLButtonElement) {
            packButton.disabled = false;
        }
    }
}

async function copyLink(): Promise<void> {
    if (!copyReady) {
        setStatus("Press Pack yourself first, then copy the link.");
        return;
    }
    let problem: Problem;
    try {
        problem = readForm();
    } catch (error) {
        setStatus(error instanceof FormError ? error.message : "The tables could not be read.");
        return;
    }
    const href = problemHref(problem);
    history.replaceState(null, "", href);
    try {
        await navigator.clipboard.writeText(href);
        setStatus("Copied the reproducer link.");
    } catch {
        setStatus(href);
    }
}

const root = document.getElementById("playground");
if (root) {
    root.addEventListener("input", markEdited);
    root.addEventListener("change", markEdited);
    const initial = readInitial();
    render(root, initial.problem);
    setNote(initial.note);
    void askWorker({ warmup: true }).catch(() => {
        // The pack request reports a loader failure on its own.
    });
    if (initial.packing) {
        draw(initial.packing);
    } else {
        void runPack(false);
    }
}
