/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
"use strict";

import {ActionManager} from "@babylonjs/core/Actions/actionManager"
import {AdvancedDynamicTexture} from "@babylonjs/gui/2D/advancedDynamicTexture";
import "@babylonjs/core/Animations/animatable";
import {Animation} from "@babylonjs/core/Animations/animation";
import {ArcRotateCamera} from "@babylonjs/core/Cameras/arcRotateCamera";
import "@babylonjs/core/Rendering/boundingBoxRenderer";
import {Color3} from "@babylonjs/core/Maths/math.color";
import {Control} from "@babylonjs/gui/2D/controls/control";
import {CreateBox} from "@babylonjs/core/Meshes/Builders/boxBuilder";
import {CreateLines} from "@babylonjs/core/Meshes/Builders/linesBuilder";
import {CreatePlane} from "@babylonjs/core/Meshes/Builders/planeBuilder";
import "@babylonjs/core/Behaviors/Meshes/pointerDragBehavior";
import {DynamicTexture} from "@babylonjs/core/Materials/Textures/dynamicTexture";
import {Engine} from "@babylonjs/core/Engines/engine";
import {ExecuteCodeAction} from "@babylonjs/core/Actions/directActions";
import {HemisphericLight} from "@babylonjs/core/Lights/hemisphericLight";
import {HighlightLayer} from "@babylonjs/core/Layers/highlightLayer";
import {Rectangle} from "@babylonjs/gui/2D/controls/rectangle";
import {Scene} from "@babylonjs/core/scene";
import {StandardMaterial} from "@babylonjs/core/Materials/standardMaterial";
import {TextBlock} from "@babylonjs/gui/2D/controls/textBlock";
import {Vector3} from "@babylonjs/core/Maths/math.vector";

document.addEventListener("DOMContentLoaded", function () {

    const DEMO_PACKING = {
        "box": {"reference": "Demo Box", "innerWidth": 100, "innerLength": 100, "innerDepth": 100},
        "items": [{
            "x": 0,
            "y": 0,
            "z": 0,
            "width": 100,
            "length": 100,
            "depth": 50,
            "item": {"description": "Demo Item #1", "width": 100, "length": 100, "depth": 50}
        }, {
            "x": 0,
            "y": 0,
            "z": 50,
            "width": 50,
            "length": 100,
            "depth": 25,
            "item": {"description": "Demo Item #2", "width": 100, "length": 50, "depth": 25}
        }]
    };

    const urlParams = new URLSearchParams(window.location.search);

    if (urlParams.has('packing')) {
        const demotext = document.getElementsByClassName('demotext') as HTMLCollectionOf<HTMLElement>
        demotext[0].style.display = 'none';
    }

    const PACKING = urlParams.has('packing') ? JSON.parse(urlParams.get('packing')!) : DEMO_PACKING;

    const ZOOM = 0.1;

    const ITEM_COLOURS = [
        new Color3(1.0, 0.0, 0.0),
        new Color3(0.0, 1.0, 0.0),
        new Color3(0.0, 0.0, 1.0),
        new Color3(1.0, 1.0, 0.0),
        new Color3(0.0, 1.0, 1.0),
        new Color3(1.0, 0.0, 1.0),
        new Color3(1.0, 1.0, 1.0),

        new Color3(0.8, 0.0, 0.0),
        new Color3(0.0, 0.8, 0.0),
        new Color3(0.0, 0.0, 0.8),
        new Color3(0.8, 0.8, 0.0),
        new Color3(0.0, 0.8, 0.8),
        new Color3(0.8, 0.0, 0.8),
        new Color3(0.8, 0.8, 0.8),

        new Color3(0.6, 0.0, 0.0),
        new Color3(0.0, 0.6, 0.0),
        new Color3(0.0, 0.0, 0.6),
        new Color3(0.6, 0.6, 0.0),
        new Color3(0.0, 0.6, 0.6),
        new Color3(0.6, 0.0, 0.6),
        new Color3(0.6, 0.6, 0.6),

        new Color3(0.4, 0.0, 0.0),
        new Color3(0.0, 0.4, 0.0),
        new Color3(0.0, 0.0, 0.4),
        new Color3(0.4, 0.4, 0.0),
        new Color3(0.0, 0.4, 0.4),
        new Color3(0.4, 0.0, 0.4),
        new Color3(0.4, 0.4, 0.4),
    ];

    const createScene = () => {
        const packingData = PACKING.items ? [PACKING] : PACKING;

        const scene = new Scene(engine);

        /*
         * Light equally from above and below. If was just boxes could use emissiveColor on the items and skip lighting
         * altogether, but we also draw axes, and they need to be lit.
         */
        const light = new HemisphericLight("hemisphere", new Vector3(0, 1, 0), scene);
        light.groundColor = new Color3(1, 1, 1);

        // Add the highlight layer.
        let hl = new HighlightLayer("hl1", scene);

        const advancedTexture = AdvancedDynamicTexture.CreateFullscreenUI("UI");
        advancedTexture.useInvalidateRectOptimization = false;

        // draw **BoxPacker** axes (y/z are flipped compared to Babylon)
        const showAxis = function (xSize: number, ySize: number, zSize: number, xPos: number) {
            let makeTextPlane = function (text: string, color: string, size: number) {
                let dynamicTexture = new DynamicTexture("DynamicTexture", 50, scene, true);
                dynamicTexture.hasAlpha = true;
                dynamicTexture.drawText(text, 5, 40, "bold 36px Arial", color, "transparent", true);
                let plane = <any>CreatePlane("TextPlane", {size: size}, scene);
                let material = new StandardMaterial("TextPlaneMaterial", scene);
                material.backFaceCulling = false;
                material.specularColor = new Color3(0, 0, 0);
                material.diffuseTexture = dynamicTexture;
                plane.material = material;
                return plane;
            };

            let axisX = CreateLines("axisX", {
                points: [
                    new Vector3(xPos, 0, 0),
                    new Vector3(xPos + xSize, 0, 0),
                    new Vector3(xPos + xSize * 0.95, 0.05 * xSize, 0),
                    new Vector3(xPos + xSize, 0, 0),
                    new Vector3(xPos + xSize * 0.95, -0.05 * xSize, 0)
                ]
            }, scene);
            axisX.color = new Color3(1, 0, 0);
            let xChar = makeTextPlane("X", "red", xSize / 10);
            xChar.position = new Vector3(0.9 * xSize + xPos, -0.05 * xSize, 0);
            let axisY = CreateLines("axisY", {
                points: [
                    new Vector3(xPos, 0, 0),
                    new Vector3(xPos, zSize, 0),
                    new Vector3(xPos - 0.05 * zSize, zSize * 0.95, 0),
                    new Vector3(xPos, zSize, 0),
                    new Vector3(xPos + 0.05 * zSize, zSize * 0.95, 0)
                ]
            }, scene);
            axisY.color = new Color3(0, 1, 0);
            let yChar = makeTextPlane("Z", "green", zSize / 10);
            yChar.position = new Vector3(xPos, 0.9 * zSize, -0.05 * zSize);
            let axisZ = CreateLines("axisZ", {
                points: [
                    new Vector3(xPos, 0, 0),
                    new Vector3(xPos, 0, ySize),
                    new Vector3(xPos, -0.05 * ySize, ySize * 0.95),
                    new Vector3(xPos, 0, ySize),
                    new Vector3(xPos, 0.05 * ySize, ySize * 0.95)
                ]
            }, scene);
            axisZ.color = new Color3(0, 0, 1);
            let zChar = makeTextPlane("Y", "blue", ySize / 10);
            zChar.position = new Vector3(xPos, 0.05 * ySize, 0.9 * ySize);
        };

        let boxPlacementX = 0;

        for (let packedBoxKey in packingData) {
            showAxis(ZOOM * packingData[packedBoxKey].box.innerWidth, ZOOM * packingData[packedBoxKey].box.innerLength, ZOOM * packingData[packedBoxKey].box.innerDepth, boxPlacementX);

            const box = CreateBox(
                "box",
                {
                    width: ZOOM * packingData[packedBoxKey].box.innerWidth,
                    depth: ZOOM * packingData[packedBoxKey].box.innerLength,
                    height: ZOOM * packingData[packedBoxKey].box.innerDepth,
                }
            );
            hl.addExcludedMesh(box);
            let material = new StandardMaterial("material", scene);
            material.alpha = 0; // make box faces invisible
            box.material = material;
            box.showBoundingBox = true; // but show edges
            // Babylon positions the centre of the box at (0,0,0) so compensate for that as BoxPacker measures from the corner
            box.position.x = boxPlacementX + ZOOM * packingData[packedBoxKey].box.innerWidth / 2;
            box.position.z = ZOOM * packingData[packedBoxKey].box.innerLength / 2;
            box.position.y = ZOOM * packingData[packedBoxKey].box.innerDepth / 2;

            for (let itemsKey = 0; itemsKey < packingData[packedBoxKey].items.length; itemsKey++) {
                let item = CreateBox(
                    "item" + itemsKey,
                    {
                        width: ZOOM * packingData[packedBoxKey].items[itemsKey].width,
                        depth: ZOOM * packingData[packedBoxKey].items[itemsKey].length,
                        height: ZOOM * packingData[packedBoxKey].items[itemsKey].depth,
                    }
                );
                let material = new StandardMaterial("material", scene);
                material.diffuseColor = ITEM_COLOURS[itemsKey % 28];
                material.alpha = 0.7;
                item.material = material;
                // Babylon positions the centre of the item at (0,0,0) not a corner so compensate for that
                item.position.x = boxPlacementX + ZOOM * packingData[packedBoxKey].items[itemsKey].width / 2 + ZOOM * packingData[packedBoxKey].items[itemsKey].x;
                item.position.z = ZOOM * packingData[packedBoxKey].items[itemsKey].length / 2 + ZOOM * packingData[packedBoxKey].items[itemsKey].y;
                item.position.y = ZOOM * packingData[packedBoxKey].items[itemsKey].depth / 2 + ZOOM * packingData[packedBoxKey].items[itemsKey].z;

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
                rect1.cornerRadius = 10
                rect1.linkWithMesh(item);

                let text1 = new TextBlock();
                text1.text = "Box: " + packingData[packedBoxKey].box.reference;
                text1.text += "\n";
                text1.text += "Item: " + packingData[packedBoxKey].items[itemsKey].item.description;
                text1.text += "\n";
                text1.text += "As specified (W×L×D): " + packingData[packedBoxKey].items[itemsKey].item.width + '×' + packingData[packedBoxKey].items[itemsKey].item.length + '×' + packingData[packedBoxKey].items[itemsKey].item.depth;
                text1.text += "\n";
                text1.text += "As packed (W×L×D): " + packingData[packedBoxKey].items[itemsKey].width + '×' + packingData[packedBoxKey].items[itemsKey].length + '×' + packingData[packedBoxKey].items[itemsKey].depth;
                text1.text += "\n";
                text1.text += "x: " + packingData[packedBoxKey].items[itemsKey].x;
                text1.text += "\n";
                text1.text += "y: " + packingData[packedBoxKey].items[itemsKey].y;
                text1.text += "\n";
                text1.text += "z: " + packingData[packedBoxKey].items[itemsKey].z;
                text1.color = "White";
                text1.fontSize = 14;
                text1.textWrapping = true;
                text1.textHorizontalAlignment = Control.HORIZONTAL_ALIGNMENT_LEFT;
                text1.textVerticalAlignment = Control.VERTICAL_ALIGNMENT_TOP;
                rect1.addControl(text1)
                text1.alpha = (1 / rect1.alpha);
                text1.paddingTop = "20px";
                text1.paddingBottom = "20px";
                text1.paddingLeft = "20px";
                text1.paddingRight = "20px";

                item.actionManager = new ActionManager(scene);

                let scaleXAnimation = new Animation("myAnimation", "scaleX", 30, Animation.ANIMATIONTYPE_FLOAT, Animation.ANIMATIONLOOPMODE_CONSTANT);
                let scaleYAnimation = new Animation("myAnimation", "scaleY", 30, Animation.ANIMATIONTYPE_FLOAT, Animation.ANIMATIONLOOPMODE_CONSTANT);

                let keys = [];
                keys.push({
                    frame: 0,
                    value: 0
                });
                keys.push({
                    frame: 10,
                    value: 1
                });

                scaleXAnimation.setKeys(keys);
                scaleYAnimation.setKeys(keys);
                (<any>rect1).animations = [scaleXAnimation, scaleYAnimation];

                item.actionManager.registerAction(new ExecuteCodeAction(ActionManager.OnPointerOverTrigger, function () {
                    hl.addMesh(item, Color3.Green());
                    scene.beginAnimation(rect1, 0, 10, false);
                }));
                //if hover is over remove highlight of the mesh
                item.actionManager.registerAction(new ExecuteCodeAction(ActionManager.OnPointerOutTrigger, function () {
                    hl.removeMesh(item);
                    scene.beginAnimation(rect1, 10, 0, false);
                }));

            }

            boxPlacementX += 2 * ZOOM * packingData[packedBoxKey].box.innerWidth
        }


        const camera = new ArcRotateCamera("ArcRotateCamera", -0.8 * Math.PI, 0.5 * Math.PI, ZOOM * packingData[0].box.innerDepth * 2.5, new Vector3(0, ZOOM * packingData[0].box.innerDepth / 2, 0), scene);
        camera.panningSensibility = 100;
        camera.attachControl(canvas, true, true);

        return scene;
    };

    const canvas = document.getElementById("renderCanvas") as HTMLCanvasElement; // Get the canvas element
    const engine = new Engine(canvas, true, {stencil: true}); // Generate the BABYLON 3D engine
    document.getElementById("makeFullscreen")?.addEventListener("click", function () {
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
