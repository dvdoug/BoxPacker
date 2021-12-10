Used / remaining space
======================

After packing it is possible to see how much physical space in each ``PackedBox`` is taken up with items,
and how much space was unused (air). This information might be useful to determine whether it would be useful to source
alternative/additional sizes of box.

At a high level, the ``getVolumeUtilisation()`` method exists which calculates how full the box is as a percentage of volume.

Lower-level methods are also available for examining this data in detail either using ``getUsed[Width|Length|Depth()]``
(a hypothetical box placed around the items) or ``getRemaining[Width|Length|Depth()]`` (the difference between the dimensions of
the actual box and the hypothetical box).

.. note::

    BoxPacker will try to pack items into the smallest box available

Example - warning on a massively oversized box
----------------------------------------------

.. code-block:: php

    <?php

        // assuming packing already took place
        foreach ($packedBoxes as $packedBox) {
            if ($packedBox->getVolumeUtilisation() < 20) {
                // box is 80% air, log a warning
            }
        }
