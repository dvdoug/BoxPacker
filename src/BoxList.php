<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

/**
 * List of boxes available to put items into, ordered by volume.
 *
 * @author Doug Wright
 */
class BoxList extends \SplMinHeap
{
    /**
     * Compare elements in order to place them correctly in the heap while sifting up.
     *
     * @see \SplMinHeap::compare()
     *
     * @param Box $boxA
     * @param Box $boxB
     *
     * @return int
     */
    public function compare($boxA, $boxB)
    {
        if ($boxB->getInnerVolume() > $boxA->getInnerVolume()) {
            return 1;
        } elseif ($boxB->getInnerVolume() < $boxA->getInnerVolume()) {
            return -1;
        } else {
            return 0;
        }
    }
}
