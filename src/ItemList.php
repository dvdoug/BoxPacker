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
use function array_key_last;
use function array_pop;
use function array_reverse;
use function array_slice;
use function count;
use function end;
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
     * @param  bool     $preSorted
     * @return ItemList
     */
    public static function fromArray(array $items, bool $preSorted = false): self
    {
        $list = new static();
        $list->list = array_reverse($items); // internal sort is largest at the end
        $list->isSorted = $preSorted;

        return $list;
    }

    /**
     * @param Item $item
     */
    public function insert(Item $item): void
    {
        $this->list[] = $item;
    }

    /**
     * Remove item from list.
     *
     * @param Item $item
     */
    public function remove(Item $item): void
    {
        foreach ($this->list as $key => $itemToCheck) {
            if ($itemToCheck === $item) {
                unset($this->list[$key]);
                break;
            }
        }
    }

    /**
     * @internal
     *
     * @return Item
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
     *
     * @return Item
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
     * @param  int      $n
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

    /**
     * @return Traversable
     */
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
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->list);
    }

    /**
     * @param Item $itemA
     * @param Item $itemB
     *
     * @return int
     */
    private function compare(Item $itemA, Item $itemB): int
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
