:orphan:

.. _visualiser:

Visualiser and demo
-------------------

.. meta::
   :description: Open a 3D view of a packing with generateVisualisationURL() on a PackedBox or PackedBoxList.

.. container:: demotext

    Although one of the principles underlying this library is that "It doesn't require the person actually packing the box
    to be given a 3D diagram explaining just how the items are supposed to fit", it is sometimes useful to do it anyway
    (it is also helpful for bug reports).

    The ``PackedBox`` objects created when a packing is calculated do contain all of the necessary information needed to
    produce your own visualisation (see :ref:`Positional information<positional_information>`). Alternatively, a basic
    visualiser is included below. You can make use of it by calling the ``generateVisualisationURL()`` method on either
    a ``PackedBox`` or a ``PackedBoxList`` which will generate a custom URL for this page.
.. _visualiser_picture:

.. raw:: html

    <div id="visualiser-picture" style="scroll-margin-top: 4rem;">
    <canvas id="renderCanvas" style="width: 100%; aspect-ratio: 1;"></canvas>
    <button id="makeFullscreen" type="button">Make full screen</button>
    </div>
    <div id="playground"></div>
    <script>
        (function () {
            var loader = document.createElement("script");
            loader.type = "module";
            loader.src = "_static/js/playground.js";
            document.currentScript.parentNode.appendChild(loader);
        })();
    </script>
