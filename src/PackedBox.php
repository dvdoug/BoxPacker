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
use function is_iterable;
use function count;
use function array_pop;
use function assert;
use function array_map;
use function spl_object_id;

use const JSON_THROW_ON_ERROR;
use const JSON_NUMERIC_CHECK;
use const JSON_UNESCAPED_UNICODE;
use const JSON_UNESCAPED_SLASHES;

/**
 * A "box" with items.
 */
readonly class PackedBox implements JsonSerializable
{
    protected int $itemWeight;

    protected float $volumeUtilisation;

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
        if (!isset($this->itemWeight)) {
            $itemWeight = 0;
            foreach ($this->items as $item) {
                $itemWeight += $item->item->getWeight();
            }
            $this->itemWeight = $itemWeight;
        }

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
            $maxWidth = max($maxWidth, $item->x + $item->width);
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
            $maxLength = max($maxLength, $item->y + $item->length);
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
            $maxDepth = max($maxDepth, $item->z + $item->depth);
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
        if (!isset($this->volumeUtilisation)) {
            $this->volumeUtilisation = round($this->getUsedVolume() / ($this->getInnerVolume() ?: 1) * 100, 1);
        }

        return $this->volumeUtilisation;
    }

    /**
     * Create a custom website visualiser URL for this packing.
     */
    public function generateVisualisationURL(): string
    {
        $dedupedItems = $splIdToIntMap = [];
        $splIdIndex = 0;
        foreach ($this->items->asItemArray() as $item) {
            if (!isset($splIdToIntMap[spl_object_id($item)])) {
                $splIdToIntMap[spl_object_id($item)] = $splIdIndex++;
            }
            $dedupedItems[$splIdToIntMap[spl_object_id($item)]] = $item;
        }

        foreach ($dedupedItems as $item) {
            $data['items'][$splIdToIntMap[spl_object_id($item)]] = [$item->getDescription(), $item->getWidth(), $item->getLength(), $item->getDepth()];
        }

        $data['boxes'][] = [
            $this->box->getReference(),
            $this->box->getInnerWidth(),
            $this->box->getInnerLength(),
            $this->box->getInnerDepth(),
            array_map(
                fn (PackedItem $item) => [$splIdToIntMap[spl_object_id($item->item)], $item->x, $item->y, $item->z, $item->width, $item->length, $item->depth],
                iterator_to_array($this->items)
            ),
        ];

        return 'https://boxpacker.io/en/master/visualiser.html?packing=' . json_encode($data, flags: JSON_THROW_ON_ERROR | JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function __construct(public Box $box, public PackedItemList $items)
    {
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
    private function assertPackingCompliesWithRealWorld(): true
    {
        /** @var PackedItem[] $itemsToCheck */
        $itemsToCheck = iterator_to_array($this->items);
        while (count($itemsToCheck) > 0) {
            $itemToCheck = array_pop($itemsToCheck);

            assert($itemToCheck->x >= 0);
            assert($itemToCheck->x + $itemToCheck->width <= $this->box->getInnerWidth());
            assert($itemToCheck->y >= 0);
            assert($itemToCheck->y + $itemToCheck->length <= $this->box->getInnerLength());
            assert($itemToCheck->z >= 0);
            assert($itemToCheck->z + $itemToCheck->depth <= $this->box->getInnerDepth());

            foreach ($itemsToCheck as $otherItem) {
                $hasXOverlap = $itemToCheck->x < ($otherItem->x + $otherItem->width) && $otherItem->x < ($itemToCheck->x + $itemToCheck->width);
                $hasYOverlap = $itemToCheck->y < ($otherItem->y + $otherItem->length) && $otherItem->y < ($itemToCheck->y + $itemToCheck->length);
                $hasZOverlap = $itemToCheck->z < ($otherItem->z + $otherItem->depth) && $otherItem->z < ($itemToCheck->z + $itemToCheck->depth);

                $hasOverlap = $hasXOverlap && $hasYOverlap && $hasZOverlap;
                assert(!$hasOverlap);
            }
        }

        return true;
    }
}
