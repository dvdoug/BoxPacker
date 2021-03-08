Rotation
========

Items
-----
BoxPacker gives you full control of how (or if) an individual item may be rotated to fit into a box, controlled via the
``getKeepFlat()`` method on the ``BoxPacker\Item`` interface.


Best fit
^^^^^^^^

To allow an item to be placed in any orientation.

.. code-block:: php

    <?php
        use DVDoug\BoxPacker\Item;

        class YourItem implements Item
        {
            public function getKeepFlat(): bool
            {
                return false;
            }
        }

Keep flat
^^^^^^^^^

For items that must be shipped "flat" or "this way up".

.. code-block:: php

    <?php
        use DVDoug\BoxPacker\Item;

        class YourItem implements Item
        {
            public function getKeepFlat(): bool
            {
                return true;
            }
        }

Boxes
-----
BoxPacker operates internally by positioning items in "rows", firstly by placing items across the width of the box,
then when there is no more space starting a new row further along the length.

However, due to the nature of the placement heuristics, better packing is sometimes achieved by going the other way
i.e. placing items along the length first. By default BoxPacker handles this by trying packing both ways around,
transposing widths and lengths as appropriate.

For most purposes this is fine, when the boxes come to be packed in real life it is done via the top and the direction
used for placement doesn't matter. However, sometimes the "box" being given to BoxPacker is actually a truck or
other side-loaded container and in these cases it is sometimes desirable to enforce the packing direction.

This can be done when using the ``VolumePacker`` by calling the ``packAcrossWidthOnly`` method.

.. code-block:: php

    <?php
    use DVDoug\BoxPacker\VolumePacker;

    $volumePacker = new VolumePacker($box, $items);
    $volumePacker->packAcrossWidthOnly();
    $packedBox = $volumePacker->pack();
