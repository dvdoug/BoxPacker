<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker\Test;

use function count;
use DVDoug\BoxPacker\Box;
use DVDoug\BoxPacker\ConstrainedItem;
use DVDoug\BoxPacker\PackedItem;
use DVDoug\BoxPacker\PackedItemList;
use function iterator_to_array;

class ConstrainedTestItem extends TestItem implements ConstrainedItem
{
    /**
     * @var int
     */
    public static $limit = 3;

    public function canBePackedInBox(PackedItemList $alreadyPackedItems, Box $box): bool
    {
        $alreadyPackedType = array_filter(
            iterator_to_array($alreadyPackedItems, false),
            function (PackedItem $item) {
                return $item->getItem()->getDescription() === $this->getDescription();
            }
        );

        return count($alreadyPackedType) + 1 <= static::$limit;
    }
}
