Advanced usage
==============

For more advanced use cases where greater control over the contents of each box is required (e.g. legal limits on the number of
hazardous items per box, or perhaps fragile items requiring an extra-strong outer box) you may implement the ``BoxPacker\ConstrainedItem``
interface which contains an additional callback method allowing you to decide whether to allow an item may be packed into a box
or not.

As with all other library methods, the objects passed into this callback are your own - you have access to their full range of
properties and methods to use when evaluating a constraint, not only those defined by the standard ``BoxPacker\Item`` interface.

Example
-------

Only allow 2 batteries per box
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: php

    <?php
        use DVDoug\BoxPacker\Box;
        use DVDoug\BoxPacker\Item;
        use DVDoug\BoxPacker\ItemList;

        class LithiumBattery implements ConstrainedItem
        {

            /**
             * @param ItemList $alreadyPackedItems
             * @param TestBox  $box
             *
             * @return bool
             */
            public function canBePackedInBox(ItemList $alreadyPackedItems, Box $box)
            {
                $batteriesPacked = 0;
                foreach ($alreadyPackedItems as $packedItem) {
                  if ($packedItem instanceof LithiumBattery) {
                      $batteriesPacked++;
                  }
                }

                if ($batteriesPacked < 2) {
                    return true;  // allowed to pack
                } else {
                    return false; // 2 batteries already packed, no more allowed in this box
                }
            }
        }

