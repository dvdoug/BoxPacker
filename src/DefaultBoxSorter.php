<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

class DefaultBoxSorter implements BoxSorter
{
    public function compare(Box $boxA, Box $boxB): int
    {
        if($boxA->getType() !== 'FlatBag'){
            $boxAVolume = $boxA->getInnerWidth() * $boxA->getInnerLength() * $boxA->getInnerDepth();
        }else{
            $boxAVolume = (int)round($boxA->getOuterWidth() * 0.65 * $boxA->getOuterWidth() * 0.25 *  ($boxA->getOuterDepth() - $boxA->getOuterWidth() * 0.35));
        }

        if($boxB->getType() !== 'FlatBag'){
            $boxBVolume = $boxB->getInnerWidth() * $boxB->getInnerLength() * $boxB->getInnerDepth();
        }else{
            $boxBVolume = (int)round($boxB->getOuterWidth() * 0.65 * $boxB->getOuterWidth() * 0.25 *  ($boxB->getOuterDepth() - $boxB->getOuterWidth() * 0.35));
        }
       
        $volumeDecider = $boxAVolume <=> $boxBVolume; // try smallest box first

        if ($volumeDecider !== 0) {
            return $volumeDecider;
        }

        $emptyWeightDecider = $boxA->getEmptyWeight() <=> $boxB->getEmptyWeight(); // with smallest empty weight
        if ($emptyWeightDecider !== 0) {
            return $emptyWeightDecider;
        }

        // maximum weight capacity as fallback decider
        return ($boxA->getMaxWeight() - $boxA->getEmptyWeight()) <=> ($boxB->getMaxWeight() - $boxB->getEmptyWeight());
    }
}
