<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use function array_key_last;
use function array_pop;
use function array_reverse;
use function array_slice;
use ArrayIterator;
use function count;
use Countable;
use function end;
use IteratorAggregate;
use Traversable;
use function usort;

/**
 * List of items to be packed, ordered by volume.
 *
 * @author Doug Wright
 */
class ItemList implements Countable, IteratorAggregate
{
    /**
     * List containing items.
     *
     * @var Item[]
     */
    private $list = [];

    /**
     * Has this list already been sorted?
     *
     * @var bool
     */
    private $isSorted = false;

    /**
     * Do a bulk create.
     *
     * @param  Item[]   $items
     * @return ItemList
     */
    public static function fromArray(array $items, bool $preSorted = false): self
    {
        $list = new static();
        $list->list = array_reverse($items); // internal sort is largest at the end
        $list->isSorted = $preSorted;

        return $list;
    }

    public function insert(Item $item): void
    {
        $this->list[] = $item;
    }

    /**
     * Remove item from list.
     */
    public function remove(Item $item): void
    {
        if (!$this->isSorted) {
            usort($this->list, [$this, 'compare']);
            $this->isSorted = true;
        }

        end($this->list);
        do {
            if (current($this->list) === $item) {
                unset($this->list[key($this->list)]);

                return;
            }
        } while (prev($this->list) !== false);
    }

    /**
     * @internal
     */
    public function extract(): Item
    {
        if (!$this->isSorted) {
            usort($this->list, [$this, 'compare']);
            $this->isSorted = true;
        }

        return array_pop($this->list);
    }

    /**
     * @internal
     */
    public function top(): Item
    {
        if (!$this->isSorted) {
            usort($this->list, [$this, 'compare']);
            $this->isSorted = true;
        }

        if (\PHP_VERSION_ID < 70300) {
            return array_slice($this->list, -1, 1)[0];
        }

        return $this->list[array_key_last($this->list)];
    }

    /**
     * @internal
     *
     * @return ItemList
     */
    public function topN(int $n): self
    {
        if (!$this->isSorted) {
            usort($this->list, [$this, 'compare']);
            $this->isSorted = true;
        }

        $topNList = new self();
        $topNList->list = array_slice($this->list, -$n, $n);
        $topNList->isSorted = true;

        return $topNList;
    }

    public function getIterator(): Traversable
    {
        if (!$this->isSorted) {
            usort($this->list, [$this, 'compare']);
            $this->isSorted = true;
        }

        return new ArrayIterator(array_reverse($this->list));
    }

    /**
     * Number of items in list.
     */
    public function count(): int
    {
        return count($this->list);
    }

    private static function compare(Item $itemA, Item $itemB): int
    {
        $volumeDecider = $itemA->getWidth() * $itemA->getLength() * $itemA->getDepth() <=> $itemB->getWidth() * $itemB->getLength() * $itemB->getDepth();
        if ($volumeDecider !== 0) {
            return $volumeDecider;
        }
        $weightDecider = $itemA->getWeight() - $itemB->getWeight();
        if ($weightDecider !== 0) {
            return $weightDecider;
        }

        return $itemB->getDescription() <=> $itemA->getDescription();
    }
}
