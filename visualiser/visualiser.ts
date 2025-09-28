/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
"use strict";

import * as BABYLON from "@babylonjs/core/Legacy/legacy";
import {AdvancedDynamicTexture, Control, Rectangle, TextBlock} from "@babylonjs/gui";

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

    const ZOOM = 0.1;

    const ITEM_COLOURS = [
        new BABYLON.Color3(1.0, 0.0, 0.0),
        new BABYLON.Color3(0.0, 1.0, 0.0),
        new BABYLON.Color3(0.0, 0.0, 1.0),
        new BABYLON.Color3(1.0, 1.0, 0.0),
        new BABYLON.Color3(0.0, 1.0, 1.0),
        new BABYLON.Color3(1.0, 0.0, 1.0),
        new BABYLON.Color3(1.0, 1.0, 1.0),

        new BABYLON.Color3(0.8, 0.0, 0.0),
        new BABYLON.Color3(0.0, 0.8, 0.0),
        new BABYLON.Color3(0.0, 0.0, 0.8),
        new BABYLON.Color3(0.8, 0.8, 0.0),
        new BABYLON.Color3(0.0, 0.8, 0.8),
        new BABYLON.Color3(0.8, 0.0, 0.8),
        new BABYLON.Color3(0.8, 0.8, 0.8),

        new BABYLON.Color3(0.6, 0.0, 0.0),
        new BABYLON.Color3(0.0, 0.6, 0.0),
        new BABYLON.Color3(0.0, 0.0, 0.6),
        new BABYLON.Color3(0.6, 0.6, 0.0),
        new BABYLON.Color3(0.0, 0.6, 0.6),
        new BABYLON.Color3(0.6, 0.0, 0.6),
        new BABYLON.Color3(0.6, 0.6, 0.6),

        new BABYLON.Color3(0.4, 0.0, 0.0),
        new BABYLON.Color3(0.0, 0.4, 0.0),
        new BABYLON.Color3(0.0, 0.0, 0.4),
        new BABYLON.Color3(0.4, 0.4, 0.0),
        new BABYLON.Color3(0.0, 0.4, 0.4),
        new BABYLON.Color3(0.4, 0.0, 0.4),
        new BABYLON.Color3(0.4, 0.4, 0.4),
    ];

    const createScene = () => {
        const scene = new BABYLON.Scene(engine);

        /*
         * Light equally from above and below. If was just boxes could use emissiveColor on the items and skip lighting
         * altogether, but we also draw axes, and they need to be lit.
         */
        const light = new BABYLON.HemisphericLight(
            "hemisphere",
            new BABYLON.Vector3(0, 1, 0),
            scene,
        );
        light.groundColor = new BABYLON.Color3(1, 1, 1);

        // Add the highlight layer.
        let hl = new BABYLON.HighlightLayer("hl1", scene);

        const advancedTexture = AdvancedDynamicTexture.CreateFullscreenUI("UI");
        advancedTexture.useInvalidateRectOptimization = false;

        // draw **BoxPacker** axes (y/z are flipped compared to Babylon)
        const showAxis = function (
            xSize: number,
            ySize: number,
            zSize: number,
            xPos: number,
        ) {
            let makeTextPlane = function (text: string, color: string, size: number) {
                let dynamicTexture = new BABYLON.DynamicTexture(
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
                let plane = <any>BABYLON.CreatePlane("TextPlane", { size: size }, scene);
                let material = new BABYLON.StandardMaterial("TextPlaneMaterial", scene);
                material.backFaceCulling = false;
                material.specularColor = new BABYLON.Color3(0, 0, 0);
                material.diffuseTexture = dynamicTexture;
                plane.material = material;
                return plane;
            };

            let axisX = BABYLON.CreateLines(
                "axisX",
                {
                    points: [
                        new BABYLON.Vector3(xPos, 0, 0),
                        new BABYLON.Vector3(xPos + xSize, 0, 0),
                        new BABYLON.Vector3(xPos + xSize * 0.95, 0.05 * xSize, 0),
                        new BABYLON.Vector3(xPos + xSize, 0, 0),
                        new BABYLON.Vector3(xPos + xSize * 0.95, -0.05 * xSize, 0),
                    ],
                },
                scene,
            );
            axisX.color = new BABYLON.Color3(1, 0, 0);
            let xChar = makeTextPlane("X", "red", xSize / 10);
            xChar.position = new BABYLON.Vector3(0.9 * xSize + xPos, -0.05 * xSize, 0);
            let axisY = BABYLON.CreateLines(
                "axisY",
                {
                    points: [
                        new BABYLON.Vector3(xPos, 0, 0),
                        new BABYLON.Vector3(xPos, zSize, 0),
                        new BABYLON.Vector3(xPos - 0.05 * zSize, zSize * 0.95, 0),
                        new BABYLON.Vector3(xPos, zSize, 0),
                        new BABYLON.Vector3(xPos + 0.05 * zSize, zSize * 0.95, 0),
                    ],
                },
                scene,
            );
            axisY.color = new BABYLON.Color3(0, 1, 0);
            let yChar = makeTextPlane("Z", "green", zSize / 10);
            yChar.position = new BABYLON.Vector3(xPos, 0.9 * zSize, -0.05 * zSize);
            let axisZ = BABYLON.CreateLines(
                "axisZ",
                {
                    points: [
                        new BABYLON.Vector3(xPos, 0, 0),
                        new BABYLON.Vector3(xPos, 0, ySize),
                        new BABYLON.Vector3(xPos, -0.05 * ySize, ySize * 0.95),
                        new BABYLON.Vector3(xPos, 0, ySize),
                        new BABYLON.Vector3(xPos, 0.05 * ySize, ySize * 0.95),
                    ],
                },
                scene,
            );
            axisZ.color = new BABYLON.Color3(0, 0, 1);
            let zChar = makeTextPlane("Y", "blue", ySize / 10);
            zChar.position = new BABYLON.Vector3(xPos, 0.05 * ySize, 0.9 * ySize);
        };

        let boxPlacementX = 0;

        packedBoxes.forEach((packedBox, index) => {
            showAxis(
                ZOOM * packedBox.width,
                ZOOM * packedBox.length,
                ZOOM * packedBox.depth,
                boxPlacementX,
            );

            const drawnBox = BABYLON.CreateBox(`Box #${index}`, {
                width: ZOOM * packedBox.width,
                depth: ZOOM * packedBox.length,
                height: ZOOM * packedBox.depth,
            });
            hl.addExcludedMesh(drawnBox);
            let material = new BABYLON.StandardMaterial("material", scene);
            material.alpha = 0; // make box faces invisible
            drawnBox.material = material;
            drawnBox.showBoundingBox = true; // but show edges
            scene.getBoundingBoxRenderer().frontColor = new BABYLON.Color3(1, 1, 1);
            scene.getBoundingBoxRenderer().backColor = new BABYLON.Color3(1, 1, 1);
            // Babylon positions the centre of the box at (0,0,0) so compensate for that as BoxPacker measures from the corner
            drawnBox.position.x = boxPlacementX + (ZOOM * packedBox.width) / 2;
            drawnBox.position.z = (ZOOM * packedBox.length) / 2;
            drawnBox.position.y = (ZOOM * packedBox.depth) / 2;

            packedBox.packedItems.forEach((packedItem: PackedItem, index) => {
                let drawnItem = BABYLON.CreateBox(`Item #${index}`, {
                    width: ZOOM * packedItem.width,
                    depth: ZOOM * packedItem.length,
                    height: ZOOM * packedItem.depth,
                });
                let material = new BABYLON.StandardMaterial("material", scene);
                material.diffuseColor = ITEM_COLOURS[index % 28];
                material.alpha = 0.7;
                drawnItem.material = material;
                // Babylon positions the centre of the item at (0,0,0) not a corner so compensate for that
                drawnItem.position.x =
                    boxPlacementX + (ZOOM * packedItem.width) / 2 + ZOOM * packedItem.x;
                drawnItem.position.z =
                    (ZOOM * packedItem.length) / 2 + ZOOM * packedItem.y;
                drawnItem.position.y =
                    (ZOOM * packedItem.depth) / 2 + ZOOM * packedItem.z;

                let rect1 = new Rectangle();
                advancedTexture.addControl(rect1);
                rect1.width = "300px";
                rect1.height = "175px";
                rect1.thickness = 2;
                rect1.linkOffsetX = "150px";
                rect1.linkOffsetY = "-100px";
                rect1.transformCenterX = 0;
                rect1.transformCenterY = 1;
                rect1.background = "grey";
                rect1.alpha = 0.7;
                rect1.scaleX = 0;
                rect1.scaleY = 0;
                rect1.cornerRadius = 10;
                rect1.linkWithMesh(drawnItem);

                let text1 = new TextBlock();
                text1.text = "Box: " + packedBox.reference;
                text1.text += "\n";
                text1.text += "Item: " + items[packedItem.itemKey].description;
                text1.text += "\n";
                text1.text +=
                    "As specified (W×L×D): " +
                    items[packedItem.itemKey].width +
                    "×" +
                    items[packedItem.itemKey].length +
                    "×" +
                    items[packedItem.itemKey].depth;
                text1.text += "\n";
                text1.text +=
                    "As packed (W×L×D): " +
                    packedItem.width +
                    "×" +
                    packedItem.length +
                    "×" +
                    packedItem.depth;
                text1.text += "\n";
                text1.text += "x: " + packedItem.x;
                text1.text += "\n";
                text1.text += "y: " + packedItem.y;
                text1.text += "\n";
                text1.text += "z: " + packedItem.z;
                text1.color = "White";
                text1.fontSize = 14;
                text1.textWrapping = true;
                text1.textHorizontalAlignment = Control.HORIZONTAL_ALIGNMENT_LEFT;
                text1.textVerticalAlignment = Control.VERTICAL_ALIGNMENT_TOP;
                rect1.addControl(text1);
                text1.alpha = 1 / rect1.alpha;
                text1.paddingTop = "20px";
                text1.paddingBottom = "20px";
                text1.paddingLeft = "20px";
                text1.paddingRight = "20px";

                drawnItem.actionManager = new BABYLON.ActionManager(scene);

                let scaleXAnimation = new BABYLON.Animation(
                    "myAnimation",
                    "scaleX",
                    30,
                    BABYLON.Animation.ANIMATIONTYPE_FLOAT,
                    BABYLON.Animation.ANIMATIONLOOPMODE_CONSTANT,
                );
                let scaleYAnimation = new BABYLON.Animation(
                    "myAnimation",
                    "scaleY",
                    30,
                    BABYLON.Animation.ANIMATIONTYPE_FLOAT,
                    BABYLON.Animation.ANIMATIONLOOPMODE_CONSTANT,
                );

                let keys = [];
                keys.push({
                    frame: 0,
                    value: 0,
                });
                keys.push({
                    frame: 10,
                    value: 1,
                });

                scaleXAnimation.setKeys(keys);
                scaleYAnimation.setKeys(keys);
                (<any>rect1).animations = [scaleXAnimation, scaleYAnimation];

                drawnItem.actionManager.registerAction(
                    new BABYLON.ExecuteCodeAction(
                        BABYLON.ActionManager.OnPointerOverTrigger,
                        function () {
                            hl.addMesh(drawnItem, BABYLON.Color3.Green());
                            scene.beginAnimation(rect1, 0, 10, false);
                        },
                    ),
                );
                //if hover is over remove highlight of the mesh
                drawnItem.actionManager.registerAction(
                    new BABYLON.ExecuteCodeAction(BABYLON.ActionManager.OnPointerOutTrigger, function () {
                        hl.removeMesh(drawnItem);
                        scene.beginAnimation(rect1, 10, 0, false);
                    }),
                );
            });

            boxPlacementX += 2 * ZOOM * packedBox.width;
        });

        const camera = new BABYLON.ArcRotateCamera(
            "ArcRotateCamera",
            -0.8 * Math.PI,
            0.5 * Math.PI,
            ZOOM * packedBoxes[0].depth * 2.5,
            new BABYLON.Vector3(0, (ZOOM * packedBoxes[0].depth) / 2, 0),
            scene,
        );
        camera.panningSensibility = 100;
        camera.attachControl(canvas, true, true);

        return scene;
    };

    const canvas = document.getElementById("renderCanvas") as unknown as HTMLCanvasElement; // Get the canvas element
    const engine = new BABYLON.Engine(canvas, true, { stencil: true }); // Generate the BABYLON 3D engine
    document
        .getElementById("makeFullscreen")
        ?.addEventListener("click", function () {
            engine.enterFullscreen(false);
        });
    // Add your code here matching the playground format
    const scene = createScene(); //Call the createScene function
    // Register a render loop to repeatedly render the scene
    engine.runRenderLoop(function () {
        scene.render();
    });
    // Watch for browser/canvas resize events
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
