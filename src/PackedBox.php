<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use JsonSerializable;

use function iterator_to_array;
use function json_encode;
use function max;
use function round;
use function urlencode;
use function is_iterable;
use function count;
use function array_pop;
use function assert;

/**
 * A "box" with items.
 */
class PackedBox implements JsonSerializable
{
    protected int $itemWeight = 0;

    protected readonly float $volumeUtilisation;

    /**
     * Get box used.
     */
    public function getBox(): Box
    {
        return $this->box;
    }

    /**
     * Get items packed.
     */
    public function getItems(): PackedItemList
    {
        return $this->items;
    }

    /**
     * Get packed weight.
     *
     * @return int weight in grams
     */
    public function getWeight(): int
    {
        return $this->box->getEmptyWeight() + $this->getItemWeight();
    }

    /**
     * Get packed weight of the items only.
     *
     * @return int weight in grams
     */
    public function getItemWeight(): int
    {
        return $this->itemWeight;
    }

    /**
     * Get remaining width inside box for another item.
     */
    public function getRemainingWidth(): int
    {
        return $this->box->getInnerWidth() - $this->getUsedWidth();
    }

    /**
     * Get remaining length inside box for another item.
     */
    public function getRemainingLength(): int
    {
        return $this->box->getInnerLength() - $this->getUsedLength();
    }

    /**
     * Get remaining depth inside box for another item.
     */
    public function getRemainingDepth(): int
    {
        return $this->box->getInnerDepth() - $this->getUsedDepth();
    }

    /**
     * Used width inside box for packing items.
     */
    public function getUsedWidth(): int
    {
        $maxWidth = 0;

        foreach ($this->items as $item) {
            $maxWidth = max($maxWidth, $item->getX() + $item->getWidth());
        }

        return $maxWidth;
    }

    /**
     * Used length inside box for packing items.
     */
    public function getUsedLength(): int
    {
        $maxLength = 0;

        foreach ($this->items as $item) {
            $maxLength = max($maxLength, $item->getY() + $item->getLength());
        }

        return $maxLength;
    }

    /**
     * Used depth inside box for packing items.
     */
    public function getUsedDepth(): int
    {
        $maxDepth = 0;

        foreach ($this->items as $item) {
            $maxDepth = max($maxDepth, $item->getZ() + $item->getDepth());
        }

        return $maxDepth;
    }

    /**
     * Get remaining weight inside box for another item.
     */
    public function getRemainingWeight(): int
    {
        return $this->box->getMaxWeight() - $this->getWeight();
    }

    public function getInnerVolume(): int
    {
        return $this->box->getInnerWidth() * $this->box->getInnerLength() * $this->box->getInnerDepth();
    }

    /**
     * Get used volume of the packed box.
     */
    public function getUsedVolume(): int
    {
        return $this->items->getVolume();
    }

    /**
     * Get unused volume of the packed box.
     */
    public function getUnusedVolume(): int
    {
        return $this->getInnerVolume() - $this->getUsedVolume();
    }

    /**
     * Get volume utilisation of the packed box.
     */
    public function getVolumeUtilisation(): float
    {
        return $this->volumeUtilisation;
    }

    /**
     * Create a custom website visualiser URL for this packing.
     */
    public function generateVisualisationURL(): string
    {
        return 'https://boxpacker.io/en/master/visualiser.html?packing=' . urlencode(json_encode($this));
    }

    public function __construct(protected Box $box, protected PackedItemList $items)
    {
        foreach ($this->items as $item) {
            $this->itemWeight += $item->getItem()->getWeight();
        }
        $this->volumeUtilisation = round($this->getUsedVolume() / ($this->getInnerVolume() ?: 1) * 100, 1);
        assert($this->assertPackingCompliesWithRealWorld());
    }

    public function jsonSerialize(): array
    {
        $userValues = [];

        if ($this->box instanceof JsonSerializable) {
            $userSerialisation = $this->box->jsonSerialize();
            if (is_iterable($userSerialisation)) {
                $userValues = $userSerialisation;
            } else {
                $userValues = ['extra' => $userSerialisation];
            }
        }

        return [
            'box' => [
                ...$userValues,
                'reference' => $this->box->getReference(),
                'innerWidth' => $this->box->getInnerWidth(),
                'innerLength' => $this->box->getInnerLength(),
                'innerDepth' => $this->box->getInnerDepth(),
            ],
            'items' => iterator_to_array($this->items),
        ];
    }

    /**
     * Validate that all items are placed solely within the confines of the box, and that no two items are placed
     * into the same physical space.
     */
    private function assertPackingCompliesWithRealWorld(): bool
    {
        /** @var PackedItem[] $itemsToCheck */
        $itemsToCheck = iterator_to_array($this->items);
        while (count($itemsToCheck) > 0) {
            $itemToCheck = array_pop($itemsToCheck);

            assert($itemToCheck->getX() >= 0);
            assert($itemToCheck->getX() + $itemToCheck->getWidth() <= $this->box->getInnerWidth());
            assert($itemToCheck->getY() >= 0);
            assert($itemToCheck->getY() + $itemToCheck->getLength() <= $this->box->getInnerLength());
            assert($itemToCheck->getZ() >= 0);
            assert($itemToCheck->getZ() + $itemToCheck->getDepth() <= $this->box->getInnerDepth());

            foreach ($itemsToCheck as $otherItem) {
                $hasXOverlap = $itemToCheck->getX() < ($otherItem->getX() + $otherItem->getWidth()) && $otherItem->getX() < ($itemToCheck->getX() + $itemToCheck->getWidth());
                $hasYOverlap = $itemToCheck->getY() < ($otherItem->getY() + $otherItem->getLength()) && $otherItem->getY() < ($itemToCheck->getY() + $itemToCheck->getLength());
                $hasZOverlap = $itemToCheck->getZ() < ($otherItem->getZ() + $otherItem->getDepth()) && $otherItem->getZ() < ($itemToCheck->getZ() + $itemToCheck->getDepth());

                $hasOverlap = $hasXOverlap && $hasYOverlap && $hasZOverlap;
                assert(!$hasOverlap);
            }
        }

        return true;
    }
}
