<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

use function usort;

/**
 * List of boxes available to put items into, ordered by volume.
 */
class BoxList implements IteratorAggregate
{
    /**
     * @var Box[]
     */
    private array $list = [];

    private bool $isSorted = false;

    private BoxSorter $sorter;

    public function __construct(BoxSorter $sorter = null)
    {
        $this->sorter = $sorter ?: new DefaultBoxSorter();
    }

    /**
     * Do a bulk create.
     *
     * @param Box[] $boxes
     */
    public static function fromArray(array $boxes, bool $preSorted = false): self
    {
        $list = new self();
        $list->list = $boxes;
        $list->isSorted = $preSorted;

        return $list;
    }

    /**
     * @return Traversable<Box>
     */
    public function getIterator(): Traversable
    {
        if (!$this->isSorted) {
            usort($this->list, [$this->sorter, 'compare']);
            $this->isSorted = true;
        }

        return new ArrayIterator($this->list);
    }

    public function insert(Box $item): void
    {
        $this->list[] = $item;
    }
}
