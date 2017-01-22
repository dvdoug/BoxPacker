<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 *
 * @package BoxPacker
 * @author Doug Wright
 */
namespace DVDoug\BoxPacker;

/**
 * List of boxes available to put items into, ordered by volume
 *
 * @author Doug Wright
 * @package BoxPacker
 */
class BoxList implements \Countable, \IteratorAggregate
{
    /**
     * @var array
     */
    protected $list = [];

    /**
     * @var bool
     */
    protected $isSorted = true;

    /**
     * @return int
     */
    public function count()
    {
        return count($this->list);
    }

    /**
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        $this->sort();
        return new \ArrayIterator($this->list);
    }

    /**
     * Insert a box choice into the list
     *
     * @param Box $box
     */
    public function insert(Box $box)
    {
        $this->list[] = $box;
        $this->isSorted = false;
    }

    /**
     * Sort the boxes into order (smallest volume first)
     */
    protected function sort()
    {
        if (!$this->isSorted) {
            usort(
                $this->list,
                function (Box $boxA, Box $boxB) {
                    if ($boxB->getInnerVolume() > $boxA->getInnerVolume()) {
                        return -1;
                    } elseif ($boxB->getInnerVolume() < $boxA->getInnerVolume()) {
                        return 1;
                    } else {
                        return 0;
                    }
                }
            );
            $this->isSorted = true;
        }
    }
}
