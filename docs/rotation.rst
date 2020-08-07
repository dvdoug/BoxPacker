Rotation
========

BoxPacker gives you full control of how (or if) an individual item may be rotated to fit into a box, controlled via the
``getAllowedRotations()`` method on the ``BoxPacker\Item`` interface.


Best fit
--------

To allow an item to be placed in any orientation.

.. code-block:: php

    <?php
        use DVDoug\BoxPacker\Item;

        class YourItem implements Item
        {
            public function getAllowedRotations(): int
            {
                return Item::ROTATION_BEST_FIT;
            }
        }

Keep flat
---------

For items that must be shipped "flat" or "this way up".

.. code-block:: php

    <?php
        use DVDoug\BoxPacker\Item;

        class YourItem implements Item
        {
            public function getAllowedRotations(): int
            {
                return Item::ROTATION_KEEP_FLAT;
            }
        }

No rotation
-----------

It is also possible to stop an item from being rotated at all. This is not normally useful for ecommerce, but can be
useful when trying to use the library in other contexts e.g. packing sprites.

.. code-block:: php

    <?php
        use DVDoug\BoxPacker\Item;

        class YourItem implements Item
        {
            public function getAllowedRotations(): int
            {
                return Item::ROTATION_NEVER;
            }
        }
