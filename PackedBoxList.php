<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
namespace DVDoug\BoxPacker;

/**
 * List of possible packed box choices, ordered by utilisation (item count, volume)
 * @author Doug Wright
 * @package BoxPacker
 */
class PackedBoxList implements \Countable, \IteratorAggregate
{

    /**
     * Average (mean) weight of boxes
     * @var float
     */
    protected $meanWeight;

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
     * @param PackedBox $box
     */
    public function insert(PackedBox $box)
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
                function (PackedBox $boxA, PackedBox $boxB) {
                    $choice = $boxB->getItems()->count() - $boxA->getItems()->count();
                    if ($choice === 0) {
                        $choice = $boxA->getBox()->getInnerVolume() - $boxB->getBox()->getInnerVolume();
                    }
                    if ($choice === 0) {
                        $choice = $boxB->getWeight() - $boxA->getWeight();
                    }
                    return $choice;
                }
            );
            $this->isSorted = true;
        }
    }

    /**
     * Reversed version of compare
     *
     * @return int
     */
    public function reverseCompare(PackedBox $boxA, PackedBox $boxB)
    {
        $choice = $boxB->getItems()->count() - $boxA->getItems()->count();
        if ($choice === 0) {
            $choice = $boxA->getBox()->getInnerVolume() - $boxB->getBox()->getInnerVolume();
        }
        if ($choice === 0) {
            $choice = $boxB->getWeight() - $boxA->getWeight();
        }
        return $choice;
    }

    /**
     * Calculate the average (mean) weight of the boxes
     *
     * @return float
     */
    public function getMeanWeight()
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
     *
     * @return float
     */
    public function getWeightVariance()
    {
        $mean = $this->getMeanWeight();

        $weightVariance = 0;
        foreach (clone $this as $box) {
            $weightVariance += pow($box->getWeight() - $mean, 2);
        }

        return $weightVariance / $this->count();

    }

    /**
     * Get volume utilisation of the set of packed boxes
     *
     * @return float
     */
    public function getVolumeUtilisation()
    {
        $itemVolume = 0;
        $boxVolume = 0;

        /** @var PackedBox $box */
        foreach (clone $this as $box) {
            $boxVolume += $box->getBox()->getInnerVolume();

            /** @var Item $item */
            foreach (clone $box->getItems() as $item) {
                $itemVolume += $item->getVolume();
            }
        }

        return round($itemVolume / $boxVolume * 100, 1);
    }

    /**
     * Do a bulk insert
     *
     * @param array $boxes
     */
    public function insertFromArray(array $boxes)
    {
        foreach ($boxes as $box) {
            $this->insert($box);
        }
    }

    /**
     * @deprecated
     *
     * @return PackedBox
     */
    public function extract() {
        $key = key($this->list);
        $obj = current($this->list);
        unset($this->list[$key]);
        return $obj;
    }
}
