/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
"use strict";
document.addEventListener("DOMContentLoaded", function (event) {

    const DEMO_PACKING = {"box":{"reference":"Demo Box","innerWidth":100,"innerLength":100,"innerDepth":100},"items":[{"x":0,"y":0,"z":0,"width":100,"length":100,"depth":50,"item":{"description":"Demo Item #1","width":100,"length":100,"depth":50}},{"x":0,"y":0,"z":50,"width":50,"length":100,"depth":25,"item":{"description":"Demo Item #2","width":100,"length":50,"depth":25}}]};

    const urlParams = new URLSearchParams(window.location.search);

    if (urlParams.has('packing')) {
        document.getElementsByClassName('demotext')[0].style.display = 'none';
    }

    const PACKING = urlParams.has('packing') ? JSON.parse(urlParams.get('packing')) : DEMO_PACKING;

    const ZOOM = 1;

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
        const packingData = PACKING.items ? [PACKING] : PACKING;

        const scene = new BABYLON.Scene(engine);

        /*
         * Light equally from above and below. If was just boxes could use emissiveColor on the items and skip lighting
         * altogether, but we also draw axes and they need to be lit.
         */
        const light = new BABYLON.HemisphericLight("hemi", new BABYLON.Vector3(0, 1, 0), scene);
        light.groundColor = new BABYLON.Color3(1, 1, 1);

        // Add the highlight layer.
        let hl = new BABYLON.HighlightLayer("hl1", scene);

        const advancedTexture = BABYLON.GUI.AdvancedDynamicTexture.CreateFullscreenUI("UI");

        // draw **BoxPacker** axes (y/z are flipped compared to Babylon)
        const showAxis = function (xSize, ySize, zSize, xPos) {
            let makeTextPlane = function (text, color, size) {
                let dynamicTexture = new BABYLON.DynamicTexture("DynamicTexture", 50, scene, true);
                dynamicTexture.hasAlpha = true;
                dynamicTexture.drawText(text, 5, 40, "bold 36px Arial", color, "transparent", true);
                let plane = new BABYLON.Mesh.CreatePlane("TextPlane", size, scene, true);
                plane.material = new BABYLON.StandardMaterial("TextPlaneMaterial", scene);
                plane.material.backFaceCulling = false;
                plane.material.specularColor = new BABYLON.Color3(0, 0, 0);
                plane.material.diffuseTexture = dynamicTexture;
                return plane;
            };

            let axisX = BABYLON.Mesh.CreateLines("axisX", [
                new BABYLON.Vector3(xPos, 0, 0), new BABYLON.Vector3(xPos + xSize, 0, 0), new BABYLON.Vector3(xPos + xSize * 0.95, 0.05 * xSize, 0),
                new BABYLON.Vector3(xPos + xSize, 0, 0), new BABYLON.Vector3(xPos + xSize * 0.95, -0.05 * xSize, 0)
            ], scene);
            axisX.color = new BABYLON.Color3(1, 0, 0);
            let xChar = makeTextPlane("X", "red", xSize / 10);
            xChar.position = new BABYLON.Vector3(0.9 * xSize + xPos, -0.05 * xSize, 0);
            let axisY = BABYLON.Mesh.CreateLines("axisY", [
                new BABYLON.Vector3(xPos, 0, 0), new BABYLON.Vector3(xPos, zSize, 0), new BABYLON.Vector3(xPos - 0.05 * zSize, zSize * 0.95, 0),
                new BABYLON.Vector3(xPos, zSize, 0), new BABYLON.Vector3(xPos + 0.05 * zSize, zSize * 0.95, 0)
            ], scene);
            axisY.color = new BABYLON.Color3(0, 1, 0);
            let yChar = makeTextPlane("Z", "green", zSize / 10);
            yChar.position = new BABYLON.Vector3(xPos, 0.9 * zSize, -0.05 * zSize);
            let axisZ = BABYLON.Mesh.CreateLines("axisZ", [
                new BABYLON.Vector3(xPos, 0, 0), new BABYLON.Vector3(xPos, 0, ySize), new BABYLON.Vector3(xPos, -0.05 * ySize, ySize * 0.95),
                new BABYLON.Vector3(xPos, 0, ySize), new BABYLON.Vector3(xPos, 0.05 * ySize, ySize * 0.95)
            ], scene);
            axisZ.color = new BABYLON.Color3(0, 0, 1);
            let zChar = makeTextPlane("Y", "blue", ySize / 10);
            zChar.position = new BABYLON.Vector3(xPos, 0.05 * ySize, 0.9 * ySize);
        };


        let boxPlacementX = 0;

        for (let packedBoxKey in packingData) {
            showAxis(ZOOM * packingData[packedBoxKey].box.innerWidth, ZOOM * packingData[packedBoxKey].box.innerLength, ZOOM * packingData[packedBoxKey].box.innerDepth, boxPlacementX);

            const box = BABYLON.MeshBuilder.CreateBox(
                "box",
                {
                    width: ZOOM * packingData[packedBoxKey].box.innerWidth,
                    depth: ZOOM * packingData[packedBoxKey].box.innerLength,
                    height: ZOOM * packingData[packedBoxKey].box.innerDepth,
                }
            );
            hl.addExcludedMesh(box);
            let material = new BABYLON.StandardMaterial("material", scene);
            material.alpha = 0; // make box faces invisible
            box.material = material;
            box.showBoundingBox = true; // but show edges
            // Babylon positions the centre of the box at (0,0,0) so compensate for that as BoxPacker measures from the corner
            box.position.x = boxPlacementX + ZOOM * packingData[packedBoxKey].box.innerWidth / 2;
            box.position.z = ZOOM * packingData[packedBoxKey].box.innerLength / 2;
            box.position.y = ZOOM * packingData[packedBoxKey].box.innerDepth / 2;

            for (let itemsKey in packingData[packedBoxKey].items) {
                let item = BABYLON.MeshBuilder.CreateBox(
                    "item" + itemsKey,
                    {
                        width: ZOOM * packingData[packedBoxKey].items[itemsKey].width,
                        depth: ZOOM * packingData[packedBoxKey].items[itemsKey].length,
                        height: ZOOM * packingData[packedBoxKey].items[itemsKey].depth,
                    }
                );
                let material = new BABYLON.StandardMaterial("material", scene);
                material.diffuseColor = ITEM_COLOURS[itemsKey % 28];
                material.alpha = 0.7;
                item.material = material;
                // Babylon positions the centre of the item at (0,0,0) not a corner so compensate for that
                item.position.x = boxPlacementX + ZOOM * packingData[packedBoxKey].items[itemsKey].width / 2 + ZOOM * packingData[packedBoxKey].items[itemsKey].x;
                item.position.z = ZOOM * packingData[packedBoxKey].items[itemsKey].length / 2 + ZOOM * packingData[packedBoxKey].items[itemsKey].y;
                item.position.y = ZOOM * packingData[packedBoxKey].items[itemsKey].depth / 2 + ZOOM * packingData[packedBoxKey].items[itemsKey].z;

                let rect1 = new BABYLON.GUI.Rectangle();
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

                let text1 = new BABYLON.GUI.TextBlock();
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
                text1.textHorizontalAlignment = BABYLON.GUI.Control.HORIZONTAL_ALIGNMENT_LEFT;
                text1.textVerticalAlignment = BABYLON.GUI.Control.VERTICAL_ALIGNMENT_TOP;
                text1.background = '#006994'
                rect1.addControl(text1)
                text1.alpha = (1 / text1.parent.alpha);
                text1.paddingTop = "20px";
                text1.paddingBottom = "20px";
                text1.paddingLeft = "20px";
                text1.paddingRight = "20px";

                let actionManager = new BABYLON.ActionManager(scene);
                item.actionManager = actionManager;

                let scaleXAnimation = new BABYLON.Animation("myAnimation", "scaleX", 30, BABYLON.Animation.ANIMATIONTYPE_FLOAT, BABYLON.Animation.ANIMATIONLOOPMODE_CONSTANT);
                let scaleYAnimation = new BABYLON.Animation("myAnimation", "scaleY", 30, BABYLON.Animation.ANIMATIONTYPE_FLOAT, BABYLON.Animation.ANIMATIONLOOPMODE_CONSTANT);

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
                rect1.animations = [];
                rect1.animations.push(scaleXAnimation);
                rect1.animations.push(scaleYAnimation);

                actionManager.registerAction(new BABYLON.ExecuteCodeAction(BABYLON.ActionManager.OnPointerOverTrigger, function (ev) {
                    hl.addMesh(item, BABYLON.Color3.Green());
                    scene.beginAnimation(rect1, 0, 10, false);
                }));
                //if hover is over remove highlight of the mesh
                actionManager.registerAction(new BABYLON.ExecuteCodeAction(BABYLON.ActionManager.OnPointerOutTrigger, function (ev) {
                    hl.removeMesh(item);
                    scene.beginAnimation(rect1, 10, 0, false);
                }));

            }

            boxPlacementX += 2 * ZOOM * packingData[packedBoxKey].box.innerWidth
        }


        const camera = new BABYLON.ArcRotateCamera("ArcRotateCamera", -0.8 * Math.PI, 0.5 * Math.PI, ZOOM * packingData[0].box.innerDepth * 2.5, new BABYLON.Vector3(0, ZOOM * packingData[0].box.innerDepth / 2, 0), scene);
        camera.panningSensibility = 100;
        camera.attachControl(canvas, true, true);

        return scene;
    };

    const canvas = document.getElementById("renderCanvas"); // Get the canvas element
    const engine = new BABYLON.Engine(canvas, true, {stencil: true}); // Generate the BABYLON 3D engine
    document.getElementById("makeFullscreen").addEventListener("click", function() {
        engine.enterFullscreen();
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
