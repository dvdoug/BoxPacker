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
 */
class PackedItemList implements Countable, IteratorAggregate
{
    /**
     * @var PackedItem[]
     */
    private array $list = [];

    private int $weight = 0;

    private int $volume = 0;

    private bool $isSorted = false;

    /**
     * @var array<string, int>
     */
    private array $linkedGroupCounts = [];

    public function insert(PackedItem $item): void
    {
        $this->list[] = $item;
        $this->weight += $item->item->getWeight();
        $this->volume += $item->width * $item->length * $item->depth;
        if ($item->item instanceof LinkedItem) {
            $group = $item->item->getLinkedItemGroup();
            $this->linkedGroupCounts[$group] = ($this->linkedGroupCounts[$group] ?? 0) + 1;
        }
    }

    /**
     * @return Traversable<PackedItem>
     */
    public function getIterator(): Traversable
    {
        if (!$this->isSorted) {
            usort($this->list, $this->compare(...));
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
        return array_map(static fn (PackedItem $packedItem) => $packedItem->item, $this->list);
    }

    /**
     * Get total volume of these items.
     */
    public function getVolume(): int
    {
        return $this->volume;
    }

    /**
     * Get total weight of these items.
     */
    public function getWeight(): int
    {
        return $this->weight;
    }

    /**
     * Get a map of linked group identifier => count of items in this list belonging to that group.
     *
     * @internal
     *
     * @return array<string, int>
     */
    public function getLinkedGroupCounts(): array
    {
        return $this->linkedGroupCounts;
    }

    private function compare(PackedItem $itemA, PackedItem $itemB): int
    {
        $itemAVolume = $itemA->item->getWidth() * $itemA->item->getLength() * $itemA->item->getDepth();
        $itemBVolume = $itemB->item->getWidth() * $itemB->item->getLength() * $itemB->item->getDepth();

        return ($itemBVolume <=> $itemAVolume) ?: ($itemB->item->getWeight() <=> $itemA->item->getWeight());
    }
}
