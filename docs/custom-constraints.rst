Custom constraints
==================

For more advanced use cases where greater control over the contents of each box is required (e.g. legal limits on the number of
hazardous items per box, or perhaps fragile items requiring an extra-strong outer box) you may implement the ``BoxPacker\ConstrainedPlacementItem``
interface which contains an additional callback method allowing you to decide whether to allow an item may be packed into a box
or not.

As with all other library methods, the objects passed into this callback are your own - you have access to their full range of
properties and methods to use when evaluating a constraint, not only those defined by the standard ``BoxPacker\Item`` interface.

Example - only allow 2 batteries per box
----------------------------------------

.. code-block:: php

    <?php
        use DVDoug\BoxPacker\PackedBox;

        class LithiumBattery implements ConstrainedPlacementItem
        {

            /**
             * Max 2 batteries per box.
             *
             * @param  Box            $box
             * @param  PackedItemList $alreadyPackedItems
             * @param  int            $proposedX
             * @param  int            $proposedY
             * @param  int            $proposedZ
             * @param  int            $width
             * @param  int            $length
             * @param  int            $depth
             * @return bool
             */
            public function canBePacked(
                Box $box,
                PackedItemList $alreadyPackedItems,
                int $proposedX,
                int $proposedY,
                int $proposedZ,
                int $width,
                int $length,
                int $depth
            ): bool {
                $batteriesPacked = 0;
                foreach ($alreadyPackedItems as $packedItem) {
                  if ($packedItem->getItem() instanceof LithiumBattery) {
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

Example - don't allow batteries to be stacked
---------------------------------------------

.. code-block:: php

    <?php
        use DVDoug\BoxPacker\PackedBox;
        use DVDoug\BoxPacker\PackedItem;

        class LithiumBattery implements ConstrainedPlacementItem
        {

            /**
             * Batteries cannot be stacked on top of each other.
             *
             * @param  Box            $box
             * @param  PackedItemList $alreadyPackedItems
             * @param  int            $proposedX
             * @param  int            $proposedY
             * @param  int            $proposedZ
             * @param  int            $width
             * @param  int            $length
             * @param  int            $depth
             * @return bool
             */
            public function canBePacked(
                Box $box,
                PackedItemList $alreadyPackedItems,
                int $proposedX,
                int $proposedY,
                int $proposedZ,
                int $width,
                int $length,
                int $depth
            ): bool {
                $alreadyPackedType = array_filter(
                    iterator_to_array($alreadyPackedItems, false),
                    function (PackedItem $item) {
                        return $item->getItem()->getDescription() === 'Battery';
                    }
                );

                /** @var PackedItem $alreadyPacked */
                foreach ($alreadyPackedType as $alreadyPacked) {
                    if (
                        $alreadyPacked->getZ() + $alreadyPacked->getDepth() === $proposedZ &&
                        $proposedX >= $alreadyPacked->getX() && $proposedX <= ($alreadyPacked->getX() + $alreadyPacked->getWidth()) &&
                        $proposedY >= $alreadyPacked->getY() && $proposedY <= ($alreadyPacked->getY() + $alreadyPacked->getLength())) {
                        return false;
                    }
                }

                return true;
            }
        }
