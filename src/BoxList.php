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

        $volumeDecider = $boxBVolume - $boxAVolume; // try smallest box first
        $emptyWeightDecider = $boxA->getEmptyWeight() - $boxB->getEmptyWeight(); // with smallest empty weight

        if ($volumeDecider !== 0) {
            return $volumeDecider;
        }
        if ($emptyWeightDecider !== 0) {
            return $emptyWeightDecider;
        }

        // maximum weight capacity as fallback decider
        $maxWeightDecider = ($boxB->getMaxWeight() - $boxB->getEmptyWeight()) - ($boxA->getMaxWeight() - $boxA->getEmptyWeight());

        if ($maxWeightDecider === 0) {
            return PHP_MAJOR_VERSION > 5 ? -1 : 1;
        }

        return $maxWeightDecider;
    }
}
