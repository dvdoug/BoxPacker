<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
declare(strict_types=1);
namespace DVDoug\BoxPacker;

/**
 * List of possible packed box choices, ordered by utilisation (item count, volume)
 * @author Doug Wright
 * @package BoxPacker
 */
class PackedBoxList extends \SplMinHeap
{

    /**
     * Average (mean) weight of boxes
     * @var float
     */
    protected $meanWeight;

    /**
     * Compare elements in order to place them correctly in the heap while sifting up.
     * @see \SplMinHeap::compare()
     *
     * @param PackedBox $boxA
     * @param PackedBox $boxB
     *
     * @return int
     */
    public function compare($boxA, $boxB): int
    {
        $choice = $boxA->getItems()->count() <=> $boxB->getItems()->count();
        if ($choice === 0) {
            $choice = $boxB->getInnerVolume() <=> $boxA->getInnerVolume();
        }
        if ($choice === 0) {
            $choice = $boxA->getWeight() <=> $boxB->getWeight();
        }
        return $choice;
    }

    /**
     * Reversed version of compare
     *
     * @param PackedBox $boxA
     * @param PackedBox $boxB
     *
     * @return int
     */
    public function reverseCompare($boxA, $boxB): int
    {
        $choice = $boxB->getItems()->count() - $boxA->getItems()->count();
        if ($choice === 0) {
            $choice = $boxA->getInnerVolume() - $boxB->getInnerVolume();
        }
        if ($choice === 0) {
            $choice = $boxB->getWeight() - $boxA->getWeight();
        }
        return $choice;
    }

    /**
     * Calculate the average (mean) weight of the boxes
     * @return float
     */
    public function getMeanWeight(): float
    {

        if (!is_null($this->meanWeight)) {
            return $this->meanWeight;
        }

        foreach (clone $this as $box) {
            $this->meanWeight += $box->getWeight();
        }

        return $this->meanWeight /= $this->count();

    }

    /**
     * Calculate the variance in weight between these boxes
     * @return float
     */
    public function getWeightVariance(): float
    {
        $mean = $this->getMeanWeight();

        $weightVariance = 0;
        foreach (clone $this as $box) {
            $weightVariance += ($box->getWeight() - $mean) ** 2;
        }

        return round($weightVariance / $this->count(), 1);

    }

    /**
     * Get volume utilisation of the set of packed boxes
     * @return float
     */
    public function getVolumeUtilisation(): float
    {
        $itemVolume = 0;
        $boxVolume = 0;

        /** @var PackedBox $box */
        foreach (clone $this as $box) {
            $boxVolume += $box->getInnerVolume();

            /** @var PackedItem $item */
            foreach (clone $box->getItems() as $item) {
                $itemVolume += ($item->getItem()->getWidth() * $item->getItem()->getLength() * $item->getItem()->getDepth());
            }
        }

        return round($itemVolume / $boxVolume * 100, 1);
    }

    /**
     * Do a bulk insert
     * @param array $boxes
     */
    public function insertFromArray(array $boxes): void
    {
        foreach ($boxes as $box) {
            $this->insert($box);
        }
    }
}
