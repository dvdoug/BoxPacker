<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

/**
 * List of items to be packed, ordered by volume.
 *
 * @author Doug Wright
 */
class ItemList extends \SplMaxHeap
{
    /**
     * Compare elements in order to place them correctly in the heap while sifting up.
     *
     * @see \SplMaxHeap::compare()
     *
     * @param mixed $itemA
     * @param mixed $itemB
     *
     * @return int
     */
    public function compare($itemA, $itemB)
    {
        if ($itemA->getVolume() > $itemB->getVolume()) {
            return 1;
        } elseif ($itemA->getVolume() < $itemB->getVolume()) {
            return -1;
        } elseif ($itemA->getWeight() !== $itemB->getWeight()) {
            return $itemA->getWeight() - $itemB->getWeight();
        } elseif ($itemA->getDescription() < $itemB->getDescription()) {
            return 1;
        } else {
            return -1;
        }
    }

    /**
     * Get copy of this list as a standard PHP array.
     *
     * @return Item[]
     */
    public function asArray()
    {
        $return = [];
        foreach (clone $this as $item) {
            $return[] = $item;
        }

        return $return;
    }
}
