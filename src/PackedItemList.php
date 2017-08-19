<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
declare(strict_types=1);
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
     * @param PackedItem $itemA
     * @param PackedItem $itemB
     *
     * @return int
     */
    public function compare($itemA, $itemB): int
    {
        $itemAVolume = $itemA->getItem()->getWidth() * $itemA->getItem()->getLength() * $itemA->getItem()->getDepth();
        $itemBVolume = $itemB->getItem()->getWidth() * $itemB->getItem()->getLength() * $itemB->getItem()->getDepth();

        if ($itemAVolume > $itemBVolume) {
            return 1;
        } elseif ($itemAVolume < $itemBVolume) {
            return -1;
        } else {
            return $itemA->getItem()->getWeight() - $itemB->getItem()->getWeight();
        }
    }

    /**
     * Get copy of this list as a standard PHP array
     * @return PackedItem[]
     */
    public function asArray(): array
    {
        $return = [];
        foreach (clone $this as $item) {
            $return[] = $item;
        }
        return $return;
    }

    /**
     * Get copy of this list as a standard PHP array
     * @return Item[]
     */
    public function asItemArray(): array
    {
        $return = [];
        foreach (clone $this as $item) {
            $return[] = $item->getItem();
        }
        return $return;
    }
}
