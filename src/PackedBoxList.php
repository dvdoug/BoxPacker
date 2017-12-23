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

/**
 * List of packed boxes.
 *
 * @author Doug Wright
 */
class PackedBoxList implements IteratorAggregate
{
    /**
     * Average (mean) weight of boxes.
     *
     * @var float
     */
    protected $meanWeight;

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
     * Number of items in list.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->list);
    }

    /**
     * @param PackedBox $item
     */
    public function insert(PackedBox $item)
    {
        $this->list[] = $item;
    }

    /**
     * Do a bulk insert.
     *
     * @internal
     *
     * @param PackedBox[] $boxes
     */
    public function insertFromArray(array $boxes): void
    {
        foreach ($boxes as $box) {
            $this->insert($box);
        }
    }

    /**
     * @internal
     *
     * @return PackedBox
     */
    public function top(): PackedBox
    {
        if (!$this->isSorted) {
            usort($this->list, [$this, 'compare']);
            $this->isSorted = true;
        }

        return reset($this->list);
    }

    /**
     * @param PackedBox $boxA
     * @param PackedBox $boxB
     *
     * @return int
     */
    private function compare(PackedBox $boxA, PackedBox $boxB): int
    {
        $choice = $boxB->getItems()->count() <=> $boxA->getItems()->count();
        if ($choice === 0) {
            $choice = $boxB->getInnerVolume() <=> $boxA->getInnerVolume();
        }
        if ($choice === 0) {
            $choice = $boxA->getWeight() <=> $boxB->getWeight();
        }

        return $choice;
    }

    /**
     * Calculate the average (mean) weight of the boxes.
     *
     * @return float
     */
    public function getMeanWeight(): float
    {
        if (!is_null($this->meanWeight)) {
            return $this->meanWeight;
        }

        /** @var PackedBox $box */
        foreach ($this->list as $box) {
            $this->meanWeight += $box->getWeight();
        }

        return $this->meanWeight /= count($this->list);
    }

    /**
     * Calculate the variance in weight between these boxes.
     *
     * @return float
     */
    public function getWeightVariance(): float
    {
        $mean = $this->getMeanWeight();

        $weightVariance = 0;
        /** @var PackedBox $box */
        foreach ($this->list as $box) {
            $weightVariance += ($box->getWeight() - $mean) ** 2;
        }

        return round($weightVariance / count($this->list), 1);
    }

    /**
     * Get volume utilisation of the set of packed boxes.
     *
     * @return float
     */
    public function getVolumeUtilisation(): float
    {
        $itemVolume = 0;
        $boxVolume = 0;

        /** @var PackedBox $box */
        foreach ($this as $box) {
            $boxVolume += $box->getInnerVolume();

            /** @var PackedItem $item */
            foreach ($box->getItems() as $item) {
                $itemVolume += ($item->getItem()->getWidth() * $item->getItem()->getLength() * $item->getItem()->getDepth());
            }
        }

        return round($itemVolume / $boxVolume * 100, 1);
    }
}
