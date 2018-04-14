<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use function is_null;
use function max;
use function round;

/**
 * A "box" with items.
 *
 * @author Doug Wright
 */
class PackedBox
{
    /**
     * Box used.
     *
     * @var Box
     */
    protected $box;

    /**
     * Items in the box.
     *
     * @var PackedItemList
     */
    protected $items;

    /**
     * Total weight of items in the box.
     *
     * @var int
     */
    protected $itemWeight;

    /**
     * Get box used.
     *
     * @return Box
     */
    public function getBox(): Box
    {
        return $this->box;
    }

    /**
     * Get items packed.
     *
     * @return PackedItemList
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
        if (!is_null($this->itemWeight)) {
            return $this->itemWeight;
        }

        $this->itemWeight = 0;
        /** @var PackedItem $item */
        foreach ($this->items as $item) {
            $this->itemWeight += $item->getItem()->getWeight();
        }

        return $this->itemWeight;
    }

    /**
     * Get remaining width inside box for another item.
     *
     * @return int
     */
    public function getRemainingWidth(): int
    {
        return $this->box->getInnerWidth() - $this->getUsedWidth();
    }

    /**
     * Get remaining length inside box for another item.
     *
     * @return int
     */
    public function getRemainingLength(): int
    {
        return $this->box->getInnerLength() - $this->getUsedLength();
    }

    /**
     * Get remaining depth inside box for another item.
     *
     * @return int
     */
    public function getRemainingDepth(): int
    {
        return $this->box->getInnerDepth() - $this->getUsedDepth();
    }

    /**
     * Used width inside box for packing items.
     *
     * @return int
     */
    public function getUsedWidth(): int
    {
        $maxWidth = 0;

        /** @var PackedItem $item */
        foreach ($this->items as $item) {
            $maxWidth = max($maxWidth, $item->getX() + $item->getWidth());
        }

        return $maxWidth;
    }

    /**
     * Used length inside box for packing items.
     *
     * @return int
     */
    public function getUsedLength(): int
    {
        $maxLength = 0;

        /** @var PackedItem $item */
        foreach ($this->items as $item) {
            $maxLength = max($maxLength, $item->getY() + $item->getLength());
        }

        return $maxLength;
    }

    /**
     * Used depth inside box for packing items.
     *
     * @return int
     */
    public function getUsedDepth(): int
    {
        $maxDepth = 0;

        /** @var PackedItem $item */
        foreach ($this->items as $item) {
            $maxDepth = max($maxDepth, $item->getZ() + $item->getDepth());
        }

        return $maxDepth;
    }

    /**
     * Get remaining weight inside box for another item.
     *
     * @return int
     */
    public function getRemainingWeight(): int
    {
        return $this->box->getMaxWeight() - $this->getWeight();
    }

    /**
     * @return int
     */
    public function getInnerVolume(): int
    {
        return $this->box->getInnerWidth() * $this->box->getInnerLength() * $this->box->getInnerDepth();
    }

    /**
     * Get used volume of the packed box.
     *
     * @return int
     */
    public function getUsedVolume(): int
    {
        $volume = 0;

        /** @var PackedItem $item */
        foreach ($this->items as $item) {
            $volume += ($item->getWidth() * $item->getLength() * $item->getDepth());
        }

        return $volume;
    }

    /**
     * Get unused volume of the packed box.
     *
     * @return int
     */
    public function getUnusedVolume(): int
    {
        return $this->getInnerVolume() - $this->getUsedVolume();
    }

    /**
     * Get volume utilisation of the packed box.
     *
     * @return float
     */
    public function getVolumeUtilisation(): float
    {
        return round($this->getUsedVolume() / $this->getInnerVolume() * 100, 1);
    }

    /**
     * Constructor.
     *
     * @param Box            $box
     * @param PackedItemList $packedItemList
     */
    public function __construct(Box $box, PackedItemList $packedItemList)
    {
        $this->box = $box;
        $this->items = $packedItemList;
    }
}
