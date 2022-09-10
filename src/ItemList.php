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
use function current;
use function end;
use function key;
use function prev;
use function usort;

use const PHP_VERSION_ID;

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
     * @var ItemSorter
     */
    private $sorter;

    /**
     * Does this list contain constrained items?
     *
     * @var bool
     */
    private $hasConstrainedItems;

    public function __construct(?ItemSorter $sorter = null)
    {
        $this->sorter = $sorter ?: new DefaultItemSorter();
    }

    /**
     * Do a bulk create.
     *
     * @param Item[] $items
     */
    public static function fromArray(array $items, bool $preSorted = false): self
    {
        $list = new static();
        $list->list = array_reverse($items); // internal sort is largest at the end
        $list->isSorted = $preSorted;

        return $list;
    }

    public function insert(Item $item, int $qty = 1): void
    {
        for ($i = 0; $i < $qty; ++$i) {
            $this->list[] = $item;
        }
        $this->isSorted = false;
        $this->hasConstrainedItems = $this->hasConstrainedItems || $item instanceof ConstrainedPlacementItem;
    }

    /**
     * Remove item from list.
     */
    public function remove(Item $item): void
    {
        if (!$this->isSorted) {
            usort($this->list, [$this->sorter, 'compare']);
            $this->list = array_reverse($this->list); // internal sort is largest at the end
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

    public function removePackedItems(PackedItemList $packedItemList): void
    {
        foreach ($packedItemList as $packedItem) {
            end($this->list);
            do {
                if (current($this->list) === $packedItem->getItem()) {
                    unset($this->list[key($this->list)]);

                    break;
                }
            } while (prev($this->list) !== false);
        }
    }

    /**
     * @internal
     */
    public function extract(): Item
    {
        if (!$this->isSorted) {
            usort($this->list, [$this->sorter, 'compare']);
            $this->list = array_reverse($this->list); // internal sort is largest at the end
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
            usort($this->list, [$this->sorter, 'compare']);
            $this->list = array_reverse($this->list); // internal sort is largest at the end
            $this->isSorted = true;
        }

        if (PHP_VERSION_ID < 70300) {
            return array_slice($this->list, -1, 1)[0];
        }

        return $this->list[array_key_last($this->list)];
    }

    /**
     * @internal
     */
    public function topN(int $n): self
    {
        if (!$this->isSorted) {
            usort($this->list, [$this->sorter, 'compare']);
            $this->list = array_reverse($this->list); // internal sort is largest at the end
            $this->isSorted = true;
        }

        $topNList = new self();
        $topNList->list = array_slice($this->list, -$n, $n);
        $topNList->isSorted = true;

        return $topNList;
    }

    /**
     * @return Traversable|Item[]
     */
    public function getIterator(): Traversable
    {
        if (!$this->isSorted) {
            usort($this->list, [$this->sorter, 'compare']);
            $this->list = array_reverse($this->list); // internal sort is largest at the end
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

    /**
     * Does this list contain items with constrained placement criteria.
     */
    public function hasConstrainedItems(): bool
    {
        if (!isset($this->hasConstrainedItems)) {
            $this->hasConstrainedItems = false;
            foreach ($this->list as $item) {
                if ($item instanceof ConstrainedPlacementItem) {
                    $this->hasConstrainedItems = true;
                    break;
                }
            }
        }

        return $this->hasConstrainedItems;
    }
}
