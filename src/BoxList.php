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
 *
 * @author Doug Wright
 */
class BoxList implements IteratorAggregate
{
    /**
     * List containing boxes.
     *
     * @var Box[]
     */
    private $list = [];

    /**
     * Has this list already been sorted?
     *
     * @var bool
     */
    private $isSorted = false;

    /**
     * @return Traversable
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
     * @param Box $item
     */
    public function insert(Box $item)
    {
        $this->list[] = $item;
    }

    /**
     * @param Box $boxA
     * @param Box $boxB
     *
     * @return int
     */
    public function compare($boxA, $boxB): int
    {
        $boxAVolume = $boxA->getInnerWidth() * $boxA->getInnerLength() * $boxA->getInnerDepth();
        $boxBVolume = $boxB->getInnerWidth() * $boxB->getInnerLength() * $boxB->getInnerDepth();

        return $boxAVolume <=> $boxBVolume;
    }
}
