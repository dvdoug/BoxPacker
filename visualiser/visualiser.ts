/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
"use strict";

// Pure barrels (Babylon 9.8+): named imports tree-shake; feature registration is explicit.
import {
    Animation,
    ArcRotateCamera,
    Color3,
    CreateBox,
    CreateLines,
    CreatePlane,
    DynamicTexture,
    Engine,
    HemisphericLight,
    Mesh,
    PointerEventTypes,
    RegisterAnimatable,
    RegisterBoundingBoxRenderer,
    RegisterEnginesExtensionsEngineDynamicTexture,
    RegisterRay,
    RegisterStandardEngineExtensions,
    Material,
    Scene,
    StandardMaterial,
    Vector3,
} from "@babylonjs/core/pure";
import {
    AdvancedDynamicTexture,
    Control,
    Rectangle,
    TextBlock,
} from "@babylonjs/gui/pure";

// Engine capabilities + scene features (must run before Engine / Scene use).
// Standard: UBO, alpha, textures, render targets, stencil.
// DynamicTexture: axis label textures (Full tier only, registered on its own).
RegisterStandardEngineExtensions();
RegisterEnginesExtensionsEngineDynamicTexture();
RegisterAnimatable(); // scene.beginAnimation / stopAnimation
RegisterRay(); // scene.pick
RegisterBoundingBoxRenderer(); // mesh.showBoundingBox

document.addEventListener("DOMContentLoaded", function () {
    const DEMO_PACKING = {
        items: [
            ["Demo Item #1", 100, 100, 50],
            ["Demo Item #2", 100, 50, 25],
        ],
        boxes: [
            [
                "Demo Box",
                100,
                100,
                100,
                [
                    [0, 0, 0, 0, 100, 100, 50],
                    [1, 0, 0, 50, 50, 100, 25],
                ],
            ],
        ],
    };

    const urlParams = new URLSearchParams(window.location.search);

    if (urlParams.has("packing")) {
        const demotext = document.getElementsByClassName(
            "demotext",
        ) as HTMLCollectionOf<HTMLElement>;
        demotext[0].style.display = "none";
    }

    const packingData = urlParams.has("packing")
        ? JSON.parse(urlParams.get("packing")!)
        : DEMO_PACKING;
    const items: Item[] = [];
    packingData.items.forEach(
        (item: readonly [string, number, number, number], index: number) => {
            items[index] = new Item(item[0], item[1], item[2], item[3]);
        },
    );

    const packedBoxes: PackedBox[] = [];
    packingData.boxes.forEach(
        (
            packedBox: readonly [
                string,
                number,
                number,
                number,
                [number, number, number, number, number, number, number][],
            ],
        ) => {
            const packedItems: PackedItem[] = [];
            packedBox[4].forEach(
                (
                    packedItem: readonly [
                        number,
                        number,
                        number,
                        number,
                        number,
                        number,
                        number,
                    ],
                ) => {
                    packedItems.push(
                        new PackedItem(
                            packedItem[0],
                            packedItem[1],
                            packedItem[2],
                            packedItem[3],
                            packedItem[4],
                            packedItem[5],
                            packedItem[6],
                        ),
                    );
                },
            );

            packedBoxes.push(
                new PackedBox(
                    packedBox[0],
                    packedBox[1],
                    packedBox[2],
                    packedBox[3],
                    packedItems,
                ),
            );
        },
    );

    // Map the full packing layout into a modest world size so float/depth
    // precision stays healthy for small mailers and large containers alike.
    // Multiple boxes sit on a roughly square grid (not one long line).
    const TARGET_WORLD_SIZE = 20;
    const boxCount = packedBoxes.length;
    const gridCols = Math.max(1, Math.ceil(Math.sqrt(boxCount)));
    const gridRows = Math.max(1, Math.ceil(boxCount / gridCols));
    let maxBoxWidth = 0;
    let maxBoxLength = 0;
    let maxBoxDepth = 0;
    for (const packedBox of packedBoxes) {
        maxBoxWidth = Math.max(maxBoxWidth, packedBox.width);
        maxBoxLength = Math.max(maxBoxLength, packedBox.length);
        maxBoxDepth = Math.max(maxBoxDepth, packedBox.depth);
    }
    // Cell pitch = box footprint + half-box gap so neighbours stay readable.
    const cellWidth = maxBoxWidth * 1.5;
    const cellLength = maxBoxLength * 1.5;
    const layoutMaxX = (gridCols - 1) * cellWidth + maxBoxWidth;
    const layoutMaxLength = (gridRows - 1) * cellLength + maxBoxLength; // Babylon Z
    const layoutMaxDepth = maxBoxDepth; // Babylon Y
    const layoutExtent = Math.max(layoutMaxX, layoutMaxDepth, layoutMaxLength, 1e-6);
    const scale = TARGET_WORLD_SIZE / layoutExtent;

    // Golden-ratio hue walk; S/V/alpha tuned interactively (0.67 / 0.80 / 0.85).
    const ITEM_SATURATION = 0.67;
    const ITEM_VALUE = 0.8;
    const ITEM_ALPHA = 0.85;
    const itemColour = (index: number): Color3 => {
        const goldenRatioConjugate = 0.618033988749895;
        const hue = ((((index * goldenRatioConjugate) % 1) + 1) % 1) * 360;
        return Color3.FromHSV(hue, ITEM_SATURATION, ITEM_VALUE);
    };

    const createScene = () => {
        const scene = new Scene(engine);

        /*
         * Light equally from above and below. If was just boxes could use emissiveColor on the items and skip lighting
         * altogether, but we also draw axes, and they need to be lit.
         */
        const light = new HemisphericLight(
            "hemisphere",
            new Vector3(0, 1, 0),
            scene,
        );
        light.groundColor = new Color3(1, 1, 1);

        // Hover: brighten the item (emissive + diffuse lift) + shared tooltip.
        // Works on every palette colour; no HighlightLayer / edges renderer.
        const ui = AdvancedDynamicTexture.CreateFullscreenUI("UI");
        ui.useInvalidateRectOptimization = false;

        type PackedItemHoverData = {
            kind: "packedItem";
            boxReference: string;
            packedItem: PackedItem;
            catalogItem: Item;
            baseDiffuse: Color3;
        };

        const tooltip = new Rectangle("itemTooltip");
        ui.addControl(tooltip);
        tooltip.width = "300px";
        tooltip.height = "175px";
        tooltip.thickness = 2;
        tooltip.linkOffsetX = "150px";
        tooltip.linkOffsetY = "-100px";
        tooltip.transformCenterX = 0;
        tooltip.transformCenterY = 1;
        tooltip.background = "grey";
        tooltip.alpha = 0.7;
        tooltip.scaleX = 0;
        tooltip.scaleY = 0;
        tooltip.cornerRadius = 10;
        // Keep the popover out of hit-testing so it cannot sit on top of picks.
        tooltip.isHitTestVisible = false;
        tooltip.isPointerBlocker = false;

        const tooltipText = new TextBlock("itemTooltipText");
        tooltip.addControl(tooltipText);
        tooltipText.color = "White";
        tooltipText.fontSize = 14;
        tooltipText.textWrapping = true;
        tooltipText.textHorizontalAlignment = Control.HORIZONTAL_ALIGNMENT_LEFT;
        tooltipText.textVerticalAlignment = Control.VERTICAL_ALIGNMENT_TOP;
        tooltipText.isHitTestVisible = false;
        tooltipText.alpha = 1 / tooltip.alpha;
        tooltipText.paddingTop = "20px";
        tooltipText.paddingBottom = "20px";
        tooltipText.paddingLeft = "20px";
        tooltipText.paddingRight = "20px";

        const scaleInKeys = [
            {frame: 0, value: 0},
            {frame: 10, value: 1},
        ];
        const scaleXAnimation = new Animation(
            "tooltipScaleX",
            "scaleX",
            30,
            Animation.ANIMATIONTYPE_FLOAT,
            Animation.ANIMATIONLOOPMODE_CONSTANT,
        );
        const scaleYAnimation = new Animation(
            "tooltipScaleY",
            "scaleY",
            30,
            Animation.ANIMATIONTYPE_FLOAT,
            Animation.ANIMATIONLOOPMODE_CONSTANT,
        );
        scaleXAnimation.setKeys(scaleInKeys);
        scaleYAnimation.setKeys(scaleInKeys);
        tooltip.animations = [scaleXAnimation, scaleYAnimation];

        let hoveredMesh: Mesh | null = null;

        const buildTooltipText = (data: PackedItemHoverData): string => {
            const {boxReference, packedItem, catalogItem} = data;
            return [
                "Box: " + boxReference,
                "Item: " + catalogItem.description,
                "As specified (W×L×D): " +
                    catalogItem.width +
                    "×" +
                    catalogItem.length +
                    "×" +
                    catalogItem.depth,
                "As packed (W×L×D): " +
                    packedItem.width +
                    "×" +
                    packedItem.length +
                    "×" +
                    packedItem.depth,
                "x: " + packedItem.x,
                "y: " + packedItem.y,
                "z: " + packedItem.z,
            ].join("\n");
        };

        const clearHoverVisual = (mesh: Mesh) => {
            const data = mesh.metadata as PackedItemHoverData | null;
            const material = mesh.material;
            if (material instanceof StandardMaterial && data?.baseDiffuse) {
                material.emissiveColor = Color3.Black();
                material.diffuseColor = data.baseDiffuse.clone();
            }
        };

        const applyHoverVisual = (mesh: Mesh) => {
            const data = mesh.metadata as PackedItemHoverData | null;
            const material = mesh.material;
            if (!(material instanceof StandardMaterial) || !data?.baseDiffuse) {
                return;
            }
            // Mild lift so hover stays clear on the brighter palette without flaring.
            material.diffuseColor = Color3.Lerp(
                data.baseDiffuse,
                Color3.White(),
                0.18,
            );
            material.emissiveColor = new Color3(0.18, 0.22, 0.14);
        };

        const clearHover = (animate: boolean) => {
            if (hoveredMesh) {
                clearHoverVisual(hoveredMesh);
                hoveredMesh = null;
            }
            scene.stopAnimation(tooltip);
            if (animate && (tooltip.scaleX > 0 || tooltip.scaleY > 0)) {
                scene.beginAnimation(tooltip, 10, 0, false);
            } else {
                tooltip.scaleX = 0;
                tooltip.scaleY = 0;
            }
        };

        const setHover = (mesh: Mesh) => {
            const data = mesh.metadata as PackedItemHoverData | null;
            if (!data || data.kind !== "packedItem") {
                clearHover(true);
                return;
            }
            if (hoveredMesh === mesh) {
                return;
            }

            clearHover(false);
            hoveredMesh = mesh;
            applyHoverVisual(mesh);
            tooltipText.text = buildTooltipText(data);
            tooltip.linkWithMesh(mesh);
            tooltip.scaleX = 0;
            tooltip.scaleY = 0;
            scene.beginAnimation(tooltip, 0, 10, false);
        };

        // Closest-hit pick among packed-item meshes only (see mesh.metadata below).
        const pickPackedItem = () =>
            scene.pick(
                scene.pointerX,
                scene.pointerY,
                (mesh) =>
                    mesh.isPickable &&
                    mesh.isVisible &&
                    mesh.isEnabled() &&
                    mesh.metadata?.kind === "packedItem",
            );

        scene.onPointerObservable.add((pointerInfo) => {
            if (pointerInfo.type !== PointerEventTypes.POINTERMOVE) {
                return;
            }
            const pickInfo = pickPackedItem();
            const mesh = pickInfo.hit ? pickInfo.pickedMesh : null;
            if (mesh instanceof Mesh) {
                setHover(mesh);
            } else {
                clearHover(true);
            }
        });
        canvas.addEventListener("pointerleave", () => {
            clearHover(true);
        });

        // Axes in group 1 (after the box/items). Clear depth for that group so
        // colored shafts are not occluded by the coplanar white bounding-box edges.
        const AXIS_RENDERING_GROUP = 1;
        scene.setRenderingAutoClearDepthStencil(AXIS_RENDERING_GROUP, true);

        // BoxPacker axes: Y/Z are swapped relative to Babylon's Y-up space.
        // xPos/zPos are the box corner on the ground plane (Babylon XZ).
        const showAxis = function (
            xSize: number,
            ySize: number,
            zSize: number,
            xPos: number,
            zPos: number,
        ) {
            // One shared origin just outside the box corner so all three shafts
            // still meet, without sitting on the white bounding-box edges.
            const o = Math.max(xSize, ySize, zSize) * 0.02;
            const origin = new Vector3(xPos - o, -o, zPos - o);

            let makeTextPlane = function (text: string, color: string, size: number) {
                let dynamicTexture = new DynamicTexture(
                    "DynamicTexture",
                    50,
                    scene,
                    true,
                );
                dynamicTexture.hasAlpha = true;
                dynamicTexture.drawText(
                    text,
                    5,
                    40,
                    "bold 36px Arial",
                    color,
                    "transparent",
                    true,
                );
                let plane = CreatePlane("TextPlane", { size: size }, scene);
                plane.isPickable = false;
                plane.renderingGroupId = AXIS_RENDERING_GROUP;
                let material = new StandardMaterial("TextPlaneMaterial", scene);
                material.backFaceCulling = false;
                material.specularColor = new Color3(0, 0, 0);
                material.diffuseTexture = dynamicTexture;
                plane.material = material;
                return plane;
            };

            const xEnd = new Vector3(origin.x + xSize, origin.y, origin.z);
            const yEnd = new Vector3(origin.x, origin.y + zSize, origin.z);
            const zEnd = new Vector3(origin.x, origin.y, origin.z + ySize);

            let axisX = CreateLines(
                "axisX",
                {
                    points: [
                        origin,
                        xEnd,
                        new Vector3(
                            origin.x + xSize * 0.95,
                            origin.y + 0.05 * xSize,
                            origin.z,
                        ),
                        xEnd,
                        new Vector3(
                            origin.x + xSize * 0.95,
                            origin.y - 0.05 * xSize,
                            origin.z,
                        ),
                    ],
                },
                scene,
            );
            axisX.isPickable = false;
            axisX.renderingGroupId = AXIS_RENDERING_GROUP;
            axisX.color = new Color3(1, 0, 0);
            let xChar = makeTextPlane("X", "red", xSize / 10);
            xChar.position = new Vector3(
                origin.x + 0.9 * xSize,
                origin.y - 0.05 * xSize,
                origin.z,
            );
            let axisY = CreateLines(
                "axisY",
                {
                    points: [
                        origin,
                        yEnd,
                        new Vector3(
                            origin.x - 0.05 * zSize,
                            origin.y + zSize * 0.95,
                            origin.z,
                        ),
                        yEnd,
                        new Vector3(
                            origin.x + 0.05 * zSize,
                            origin.y + zSize * 0.95,
                            origin.z,
                        ),
                    ],
                },
                scene,
            );
            axisY.isPickable = false;
            axisY.renderingGroupId = AXIS_RENDERING_GROUP;
            axisY.color = new Color3(0, 1, 0);
            let yChar = makeTextPlane("Z", "green", zSize / 10);
            yChar.position = new Vector3(
                origin.x,
                origin.y + 0.9 * zSize,
                origin.z - 0.05 * zSize,
            );
            let axisZ = CreateLines(
                "axisZ",
                {
                    points: [
                        origin,
                        zEnd,
                        new Vector3(
                            origin.x,
                            origin.y - 0.05 * ySize,
                            origin.z + ySize * 0.95,
                        ),
                        zEnd,
                        new Vector3(
                            origin.x,
                            origin.y + 0.05 * ySize,
                            origin.z + ySize * 0.95,
                        ),
                    ],
                },
                scene,
            );
            axisZ.isPickable = false;
            axisZ.renderingGroupId = AXIS_RENDERING_GROUP;
            axisZ.color = new Color3(0, 0, 1);
            let zChar = makeTextPlane("Y", "blue", ySize / 10);
            zChar.position = new Vector3(
                origin.x,
                origin.y + 0.05 * ySize,
                origin.z + 0.9 * ySize,
            );
        };

        packedBoxes.forEach((packedBox, index) => {
            const col = index % gridCols;
            const row = Math.floor(index / gridCols);
            const boxPlacementX = scale * col * cellWidth;
            const boxPlacementZ = scale * row * cellLength;

            showAxis(
                scale * packedBox.width,
                scale * packedBox.length,
                scale * packedBox.depth,
                boxPlacementX,
                boxPlacementZ,
            );

            const drawnBox = CreateBox(`Box #${index}`, {
                width: scale * packedBox.width,
                depth: scale * packedBox.length,
                height: scale * packedBox.depth,
            });
            // Invisible shell for the outer box outline; keep it unpickable.
            drawnBox.isPickable = false;
            let material = new StandardMaterial("material", scene);
            material.alpha = 0;
            // Faces must not write depth or they hide coplanar axis lines / BB edges.
            material.disableDepthWrite = true;
            drawnBox.material = material;
            drawnBox.showBoundingBox = true;
            scene.getBoundingBoxRenderer().frontColor = new Color3(1, 1, 1);
            scene.getBoundingBoxRenderer().backColor = new Color3(1, 1, 1);
            // Babylon places a box at its centre; BoxPacker measures from the corner.
            drawnBox.position.x = boxPlacementX + (scale * packedBox.width) / 2;
            drawnBox.position.z = boxPlacementZ + (scale * packedBox.length) / 2;
            drawnBox.position.y = (scale * packedBox.depth) / 2;

            packedBox.packedItems.forEach((packedItem: PackedItem, itemIndex) => {
                let drawnItem = CreateBox(
                    `Item #${index}.${itemIndex}`,
                    {
                        width: scale * packedItem.width,
                        depth: scale * packedItem.length,
                        height: scale * packedItem.depth,
                    },
                );
                let itemMaterial = new StandardMaterial(
                    `ItemMaterial #${index}.${itemIndex}`,
                    scene,
                );
                // Colour by catalog key so the same product matches across boxes.
                const baseDiffuse = itemColour(packedItem.itemKey);
                itemMaterial.diffuseColor = baseDiffuse;
                itemMaterial.alpha = ITEM_ALPHA;
                // Stable-ish transparency: depth pre-pass + alpha blend (see below).
                itemMaterial.transparencyMode = Material.MATERIAL_ALPHABLEND;
                itemMaterial.needDepthPrePass = true;
                drawnItem.material = itemMaterial;
                // Same centre-vs-corner adjustment as the outer box.
                drawnItem.position.x =
                    boxPlacementX +
                    (scale * packedItem.width) / 2 +
                    scale * packedItem.x;
                drawnItem.position.z =
                    boxPlacementZ +
                    (scale * packedItem.length) / 2 +
                    scale * packedItem.y;
                drawnItem.position.y =
                    (scale * packedItem.depth) / 2 + scale * packedItem.z;

                // Used by pickPackedItem / tooltip content / hover colour restore.
                drawnItem.metadata = {
                    kind: "packedItem",
                    boxReference: packedBox.reference,
                    packedItem,
                    catalogItem: items[packedItem.itemKey],
                    baseDiffuse,
                } satisfies PackedItemHoverData;
            });
        });

        // Frame the whole grid so zoom stays about the layout centre (works for
        // far cells without a separate “focus box” interaction).
        const sceneSize =
            Math.max(layoutMaxX, layoutMaxDepth, layoutMaxLength) * scale;
        const target = new Vector3(
            (scale * layoutMaxX) / 2,
            (scale * layoutMaxDepth) / 2,
            (scale * layoutMaxLength) / 2,
        );
        const radius = sceneSize * 2.5;

        const camera = new ArcRotateCamera(
            "ArcRotateCamera",
            -0.8 * Math.PI,
            0.5 * Math.PI,
            radius,
            target,
            scene,
        );
        camera.panningSensibility = 100;
        camera.attachControl(canvas, true, true);

        return scene;
    };

    const canvas = document.getElementById(
        "renderCanvas",
    ) as unknown as HTMLCanvasElement;
    const engine = new Engine(canvas, true);
    document
        .getElementById("makeFullscreen")
        ?.addEventListener("click", function () {
            engine.enterFullscreen(false);
        });
    const scene = createScene();
    engine.runRenderLoop(function () {
        scene.render();
    });
    window.addEventListener("resize", function () {
        engine.resize();
    });
});

class Item {
    constructor(
        public readonly description: string,
        public readonly width: number,
        public readonly length: number,
        public readonly depth: number,
    ) {}
}

class PackedBox {
    constructor(
        public readonly reference: string,
        public readonly width: number,
        public readonly length: number,
        public readonly depth: number,
        public readonly packedItems: PackedItem[],
    ) {}
}

class PackedItem {
    constructor(
        public readonly itemKey: number,
        public readonly x: number,
        public readonly y: number,
        public readonly z: number,
        public readonly width: number,
        public readonly length: number,
        public readonly depth: number,
    ) {}
}
