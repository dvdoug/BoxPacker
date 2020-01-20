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
        $boxAVolume = $boxA->getInnerWidth() * $boxA->getInnerLength() * $boxA->getInnerDepth();
        $boxBVolume = $boxB->getInnerWidth() * $boxB->getInnerLength() * $boxB->getInnerDepth();

        // try smallest box first
        if ($boxBVolume > $boxAVolume) {
            return 1;
        }
        if ($boxAVolume > $boxBVolume) {
            return -1;
        }

        // smallest empty weight
        if ($boxB->getEmptyWeight() > $boxA->getEmptyWeight()) {
            return 1;
        }
        if ($boxA->getEmptyWeight() > $boxB->getEmptyWeight()) {
            return -1;
        }

        // maximum weight capacity as fallback decider
        if (($boxA->getMaxWeight() - $boxA->getEmptyWeight()) > ($boxB->getMaxWeight() - $boxB->getEmptyWeight())) {
            return -1;
        }
        if (($boxB->getMaxWeight() - $boxB->getEmptyWeight()) > ($boxA->getMaxWeight() - $boxA->getEmptyWeight())) {
            return 1;
        }

        return 0;
    }
}
