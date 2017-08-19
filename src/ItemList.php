<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
declare(strict_types=1);
namespace DVDoug\BoxPacker;

/**
 * List of items to be packed, ordered by volume
 * @author Doug Wright
 * @package BoxPacker
 */
class ItemList extends \SplMaxHeap
{

    /**
     * Compare elements in order to place them correctly in the heap while sifting up.
     *
     * @see \SplMaxHeap::compare()
     *
     * @param Item $itemA
     * @param Item $itemB
     *
     * @return int
     */
    public function compare($itemA, $itemB): int
    {
        $itemAVolume = $itemA->getWidth() * $itemA->getLength() * $itemA->getDepth();
        $itemBVolume = $itemB->getWidth() * $itemB->getLength() * $itemB->getDepth();
        return ($itemAVolume <=> $itemBVolume) ?: ($itemA->getWeight() - $itemB->getWeight());
    }

    /**
     * Get copy of this list as a standard PHP array
     * @return array
     */
    public function asArray(): array
    {
        $return = [];
        foreach (clone $this as $item) {
            $return[] = $item;
        }
        return $return;
    }
}
