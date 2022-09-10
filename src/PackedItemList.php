<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

use function array_map;
use function count;
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

    private $weight = 0;

    /**
     * Has this list already been sorted?
     *
     * @var bool
     */
    private $isSorted = false;

    public function insert(PackedItem $item): void
    {
        $this->list[] = $item;
        $this->weight += $item->getItem()->getWeight();
    }

    /**
     * @return Traversable|PackedItem[]
     */
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

    /**
     * Get total volume of these items.
     */
    public function getVolume(): int
    {
        $volume = 0;

        foreach ($this->list as $item) {
            $volume += $item->getVolume();
        }

        return $volume;
    }

    /**
     * Get total weight of these items.
     */
    public function getWeight(): int
    {
        return $this->weight;
    }

    private function compare(PackedItem $itemA, PackedItem $itemB): int
    {
        $itemAVolume = $itemA->getItem()->getWidth() * $itemA->getItem()->getLength() * $itemA->getItem()->getDepth();
        $itemBVolume = $itemB->getItem()->getWidth() * $itemB->getItem()->getLength() * $itemB->getItem()->getDepth();

        return ($itemBVolume <=> $itemAVolume) ?: ($itemB->getItem()->getWeight() <=> $itemA->getItem()->getWeight());
    }
}
