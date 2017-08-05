<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker\Test;

use DVDoug\BoxPacker\Box;
use DVDoug\BoxPacker\ConstrainedItem;
use DVDoug\BoxPacker\PackedItem;
use DVDoug\BoxPacker\PackedItemList;

class TestConstrainedTestItem extends TestItem implements ConstrainedItem
{
    /**
     * @var int
     */
    public static $limit = 3;

    /**
     * @param PackedItemList $alreadyPackedItems
     * @param TestBox  $box
     *
     * @return bool
     */
    public function canBePackedInBox(PackedItemList $alreadyPackedItems, Box $box)
    {
        $alreadyPackedType = array_filter(
            $alreadyPackedItems->asArray(),
            function(PackedItem $item) {
                return $item->getItem()->getDescription() === $this->getDescription();
            }
        );

        return count($alreadyPackedType) + 1 <= static::$limit;
    }
}

