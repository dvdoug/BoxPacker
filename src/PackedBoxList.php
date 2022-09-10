<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use ReturnTypeWillChange;
use Traversable;

use function count;
use function reset;
use function round;

/**
 * List of packed boxes.
 *
 * @author Doug Wright
 */
class PackedBoxList implements IteratorAggregate, Countable, JsonSerializable
{
    /**
     * List containing boxes.
     *
     * @var PackedBox[]
     */
    private $list = [];

    /**
     * @return Traversable|PackedBox[]
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->list);
    }

    /**
     * Number of items in list.
     */
    public function count(): int
    {
        return count($this->list);
    }

    public function insert(PackedBox $item): void
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
     */
    public function top(): PackedBox
    {
        return reset($this->list);
    }

    /**
     * Calculate the average (mean) weight of the boxes.
     */
    public function getMeanWeight(): float
    {
        $meanWeight = 0;

        /** @var PackedBox $box */
        foreach ($this->list as $box) {
            $meanWeight += $box->getWeight();
        }

        return $meanWeight / count($this->list);
    }

    /**
     * Calculate the average (mean) weight of the boxes.
     */
    public function getMeanItemWeight(): float
    {
        $meanWeight = 0;

        /** @var PackedBox $box */
        foreach ($this->list as $box) {
            $meanWeight += $box->getItemWeight();
        }

        return $meanWeight / count($this->list);
    }

    /**
     * Calculate the variance in weight between these boxes.
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

    #[ReturnTypeWillChange]
    public function jsonSerialize()/* : mixed */
    {
        return $this->list;
    }
}
