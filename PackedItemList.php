<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
namespace DVDoug\BoxPacker;

/**
 * List of packed items, ordered by volume
 * @author Doug Wright
 * @package BoxPacker
 */
class PackedItemList extends \SplMaxHeap
{

    /**
     * Compare elements in order to place them correctly in the heap while sifting up.
     *
     * @see \SplMaxHeap::compare()
     *
     * @param Item $itemA
     * @param Item $itemB
     *
     * @return int|void
     */
    public function compare($itemA, $itemB)
    {
        if ($itemA->getItem()->getVolume() > $itemB->getItem()->getVolume()) {
            return 1;
        } elseif ($itemA->getItem()->getVolume() < $itemB->getItem()->getVolume()) {
            return -1;
        } else {
            return $itemA->getItem()->getWeight() - $itemB->getItem()->getWeight();
        }
    }

    /**
     * Get copy of this list as a standard PHP array
     * @return array
     */
    public function asArray()
    {
        $return = [];
        foreach (clone $this as $item) {
            $return[] = $item;
        }
        return $return;
    }

    /**
     * Get copy of this list as a standard PHP array
     * @return array
     */
    public function asItemArray()
    {
        $return = [];
        foreach (clone $this as $item) {
            $return[] = $item->getItem();
        }
        return $return;
    }
}
