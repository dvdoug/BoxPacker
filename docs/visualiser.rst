:orphan:

.. _visualiser:

Visualiser
----------

.. container:: demotext

    Although one of the principles underlying this library is that "It doesn't require the person actually packing the box
    to be given a 3D diagram explaining just how the items are supposed to fit", it is sometimes useful to do it anyway.

    The ``PackedBox`` objects created when a packing is calculated do contain all of the necessary information needed to
    produce your own visualisation (see :ref:`Positional information<positional_information>`). Alternatively, a basic
    visualiser is included below. You can make use of it by calling the ``generateVisualisationURL()`` method on either
    a ``PackedBox`` or a ``PackedBoxList`` which will generate a custom URL for this page.

.. raw:: html

    <canvas id="renderCanvas" style="width: 100%; aspect-ratio: 1;"></canvas>
    <button id="makeFullscreen">Make full screen</button>
