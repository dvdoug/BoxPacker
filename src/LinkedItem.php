<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

/**
 * An item to be packed where all items sharing the same group identifier must end up in the same box.
 * Only implement this interface if you actually need this additional functionality as it will slow down
 * the packing algorithm.
 */
interface LinkedItem extends Item
{
    /**
     * Returns the group identifier for this item. All items returning the same non-empty identifier
     * will be packed into the same box. An empty string is invalid and will cause an exception.
     */
    public function getLinkedItemGroup(): string;
}
