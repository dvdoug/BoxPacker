<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use function array_map;
use ArrayIterator;
use function count;
use Countable;
use IteratorAggregate;
use Traversable;
use function usort;

/**
 * List of packed items, ordered by volume.
 *
 * @author Doug Wright
 */
class PackedItemList implements Countable, IteratorAggregate
{
    /**
     * List containing items.
     *
     * @var PackedItem[]
     */
    private $list = [];

    /**
     * Has this list already been sorted?
     *
     * @var bool
     */
    private $isSorted = false;

    public function insert(PackedItem $item): void
    {
        $this->list[] = $item;
    }

    public function getIterator(): Traversable
    {
        if (!$this->isSorted) {
            usort($this->list, [$this, 'compare']);
            $this->isSorted = true;
        }

        return new ArrayIterator($this->list);
    }

    /**
     * Number of items in list.
     */
    public function count(): int
    {
        return count($this->list);
    }

    /**
     * Get copy of this list as a standard PHP array.
     *
     * @internal
     *
     * @return Item[]
     */
    public function asItemArray(): array
    {
        return array_map(function (PackedItem $packedItem) {
            return $packedItem->getItem();
        }, $this->list);
    }

    private function compare(PackedItem $itemA, PackedItem $itemB): int
    {
        $itemAVolume = $itemA->getItem()->getWidth() * $itemA->getItem()->getLength() * $itemA->getItem()->getDepth();
        $itemBVolume = $itemB->getItem()->getWidth() * $itemB->getItem()->getLength() * $itemB->getItem()->getDepth();

        return ($itemBVolume <=> $itemAVolume) ?: ($itemB->getItem()->getWeight() <=> $itemA->getItem()->getWeight());
    }
}
