<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
declare(strict_types=1);
namespace DVDoug\BoxPacker;

/**
 * A "box" with items
 * @author Doug Wright
 * @package BoxPacker
 */
class PackedBox
{

    /**
     * Box used
     * @var Box
     */
    protected $box;

    /**
     * Items in the box
     * @var PackedItemList
     */
    protected $items;

    /**
     * Total weight of box
     * @var int
     */
    protected $weight;

    /**
     * Get box used
     * @return Box
     */
    public function getBox(): Box
    {
        return $this->box;
    }

    /**
     * Get items packed
     * @return PackedItemList
     */
    public function getItems(): PackedItemList
    {
        return $this->items;
    }

    /**
     * Get packed weight
     * @return int weight in grams
     */
    public function getWeight(): int
    {
        if (!is_null($this->weight)) {
            return $this->weight;
        }

        $this->weight = $this->box->getEmptyWeight();
        $items = clone $this->items;
        /** @var PackedItem $item */
        foreach ($items as $item) {
            $this->weight += $item->getItem()->getWeight();
        }
        return $this->weight;
    }

    /**
     * Get remaining width inside box for another item
     * @return int
     */
    public function getRemainingWidth(): int
    {
        return $this->box->getInnerWidth() - $this->getUsedWidth();
    }

    /**
     * Get remaining length inside box for another item
     * @return int
     */
    public function getRemainingLength(): int
    {
        return $this->box->getInnerLength() - $this->getUsedLength();
    }

    /**
     * Get remaining depth inside box for another item
     * @return int
     */
    public function getRemainingDepth(): int
    {
        return $this->box->getInnerDepth() - $this->getUsedDepth();
    }

    /**
     * Used width inside box for packing items
     * @return int
     */
    public function getUsedWidth(): int
    {
        $maxWidth = 0;

        /** @var PackedItem $item */
        foreach (clone $this->items as $item) {
            $maxWidth = max($maxWidth, $item->getX() + $item->getWidth());
        }

        return $maxWidth;
    }

    /**
     * Used length inside box for packing items
     * @return int
     */
    public function getUsedLength(): int
    {
        $maxLength = 0;

        /** @var PackedItem $item */
        foreach (clone $this->items as $item) {
            $maxLength = max($maxLength, $item->getY() + $item->getLength());
        }

        return $maxLength;
    }

    /**
     * Used depth inside box for packing items
     * @return int
     */
    public function getUsedDepth(): int
    {
        $maxDepth = 0;

        /** @var PackedItem $item */
        foreach (clone $this->items as $item) {
            $maxDepth = max($maxDepth, $item->getZ() + $item->getDepth());
        }

        return $maxDepth;
    }

    /**
     * Get remaining weight inside box for another item
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
     * Get volume utilisation of the packed box
     * @return float
     */
    public function getVolumeUtilisation(): float
    {
        $itemVolume = 0;

        /** @var PackedItem $item */
        foreach (clone $this->items as $item) {
            $itemVolume += ($item->getItem()->getWidth() * $item->getItem()->getLength() * $item->getItem()->getDepth());
        }

        return round($itemVolume / $this->getInnerVolume() * 100, 1);
    }


    /**
     * Constructor
     *
     * @param Box      $box
     * @param PackedItemList $itemList
     */
    public function __construct(Box $box, PackedItemList $itemList)
    {
        $this->box = $box;
        $this->items = $itemList;
    }
}
