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
use Traversable;

use function count;
use function json_encode;
use function reset;
use function round;
use function usort;
use function array_map;
use function iterator_to_array;
use function spl_object_id;

use const JSON_THROW_ON_ERROR;
use const JSON_NUMERIC_CHECK;
use const JSON_UNESCAPED_UNICODE;
use const JSON_UNESCAPED_SLASHES;

/**
 * List of packed boxes.
 */
class PackedBoxList implements IteratorAggregate, Countable, JsonSerializable
{
    /**
     * @var PackedBox[]
     */
    private array $list = [];

    private bool $isSorted = false;

    public function __construct(private readonly PackedBoxSorter $sorter = new DefaultPackedBoxSorter())
    {
    }

    /**
     * @return Traversable<PackedBox>
     */
    public function getIterator(): Traversable
    {
        if (!$this->isSorted) {
            usort($this->list, $this->sorter->compare(...));
            $this->isSorted = true;
        }

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
        $this->isSorted = false;
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
        if (!$this->isSorted) {
            usort($this->list, $this->sorter->compare(...));
            $this->isSorted = true;
        }

        return reset($this->list);
    }

    /**
     * Calculate the average (mean) weight of the boxes.
     */
    public function getMeanWeight(): float
    {
        $meanWeight = 0;

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

        foreach ($this as $box) {
            $boxVolume += $box->getInnerVolume();

            foreach ($box->items as $item) {
                $itemVolume += ($item->item->getWidth() * $item->item->getLength() * $item->item->getDepth());
            }
        }

        return round($itemVolume / $boxVolume * 100, 1);
    }

    /**
     * Create a custom website visualiser URL for this packing.
     */
    public function generateVisualisationURL(): string
    {
        $items = [];
        foreach ($this->list as $packedBox) {
            $items = [...$items, ...$packedBox->items->asItemArray()];
        }
        $dedupedItems = $splIdToIntMap = [];
        $splIdIndex = 0;
        foreach ($items as $item) {
            if (!isset($splIdToIntMap[spl_object_id($item)])) {
                $splIdToIntMap[spl_object_id($item)] = $splIdIndex++;
            }
            $dedupedItems[$splIdToIntMap[spl_object_id($item)]] = $item;
        }

        foreach ($dedupedItems as $item) {
            $data['items'][$splIdToIntMap[spl_object_id($item)]] = [$item->getDescription(), $item->getWidth(), $item->getLength(), $item->getDepth()];
        }

        $data['boxes'] = [];
        foreach ($this->list as $packedBox) {
            $data['boxes'][] = [
                $packedBox->box->getReference(),
                $packedBox->box->getInnerWidth(),
                $packedBox->box->getInnerLength(),
                $packedBox->box->getInnerDepth(),
                array_map(
                    fn (PackedItem $item) => [$splIdToIntMap[spl_object_id($item->item)], $item->x, $item->y, $item->z, $item->width, $item->length, $item->depth],
                    iterator_to_array($packedBox->items)
                ),
            ];
        }

        return 'https://boxpacker.io/en/master/visualiser.html?packing=' . json_encode($data, flags: JSON_THROW_ON_ERROR | JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function jsonSerialize(): array
    {
        return $this->list;
    }
}
