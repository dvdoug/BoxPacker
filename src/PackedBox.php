<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

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
     * @var ItemList
     */
    protected $items;

    /**
     * Total weight of box.
     *
     * @var int
     */
    protected $weight;

    /**
     * Total weight of items in the box.
     *
     * @var int
     */
    protected $itemWeight;

    /**
     * Remaining width inside box for another item.
     *
     * @var int
     */
    protected $remainingWidth;

    /**
     * Remaining length inside box for another item.
     *
     * @var int
     */
    protected $remainingLength;

    /**
     * Remaining depth inside box for another item.
     *
     * @var int
     */
    protected $remainingDepth;

    /**
     * Remaining weight inside box for another item.
     *
     * @var int
     */
    protected $remainingWeight;

    /**
     * Used width inside box for packing items.
     *
     * @var int
     */
    protected $usedWidth;

    /**
     * Used length inside box for packing items.
     *
     * @var int
     */
    protected $usedLength;

    /**
     * Used depth inside box for packing items.
     *
     * @var int
     */
    protected $usedDepth;

    /**
     * Get box used.
     *
     * @return Box
     */
    public function getBox()
    {
        return $this->box;
    }

    /**
     * Get items packed.
     *
     * @return ItemList
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Get packed weight.
     *
     * @return int weight in grams
     */
    public function getWeight()
    {
        return $this->box->getEmptyWeight() + $this->getItemWeight();
    }

    /**
     * Get packed weight of the items only.
     *
     * @return int weight in grams
     */
    public function getItemWeight()
    {
        if (!is_null($this->itemWeight)) {
            return $this->itemWeight;
        }
        $this->itemWeight = 0;
        /** @var Item $item */
        foreach (clone $this->items as $item) {
            $this->itemWeight += $item->getWeight();
        }

        return $this->itemWeight;
    }

    /**
     * Get remaining width inside box for another item.
     *
     * @return int
     */
    public function getRemainingWidth()
    {
        return $this->remainingWidth;
    }

    /**
     * Get remaining length inside box for another item.
     *
     * @return int
     */
    public function getRemainingLength()
    {
        return $this->remainingLength;
    }

    /**
     * Get remaining depth inside box for another item.
     *
     * @return int
     */
    public function getRemainingDepth()
    {
        return $this->remainingDepth;
    }

    /**
     * Used width inside box for packing items.
     *
     * @return int
     */
    public function getUsedWidth()
    {
        return $this->usedWidth;
    }

    /**
     * Used length inside box for packing items.
     *
     * @return int
     */
    public function getUsedLength()
    {
        return $this->usedLength;
    }

    /**
     * Used depth inside box for packing items.
     *
     * @return int
     */
    public function getUsedDepth()
    {
        return $this->usedDepth;
    }

    /**
     * Get remaining weight inside box for another item.
     *
     * @return int
     */
    public function getRemainingWeight()
    {
        return $this->remainingWeight;
    }

    /**
     * @return int
     */
    public function getInnerVolume()
    {
        return $this->box->getInnerWidth() * $this->box->getInnerLength() * $this->box->getInnerDepth();
    }

    /**
     * Get used volume of the packed box.
     *
     * @return int
     */
    public function getUsedVolume()
    {
        $volume = 0;
        /** @var PackedItem $item */
        foreach (clone $this->items as $item) {
            $volume += ($item->getWidth() * $item->getLength() * $item->getDepth());
        }

        return $volume;
    }

    /**
     * Get unused volume of the packed box.
     *
     * @return int
     */
    public function getUnusedVolume()
    {
        return $this->getInnerVolume() - $this->getUsedVolume();
    }

    /**
     * Get volume utilisation of the packed box.
     *
     * @return float
     */
    public function getVolumeUtilisation()
    {
        $itemVolume = 0;

        /** @var Item $item */
        foreach (clone $this->items as $item) {
            $itemVolume += $item->getVolume();
        }

        return round($itemVolume / $this->box->getInnerVolume() * 100, 1);
    }

    /**
     * Legacy constructor.
     *
     * @deprecated
     *
     * @param Box      $box
     * @param ItemList $itemList
     * @param int      $remainingWidth
     * @param int      $remainingLength
     * @param int      $remainingDepth
     * @param int      $remainingWeight
     * @param int      $usedWidth
     * @param int      $usedLength
     * @param int      $usedDepth
     */
    public function __construct(
        Box $box,
        ItemList $itemList,
        $remainingWidth,
        $remainingLength,
        $remainingDepth,
        $remainingWeight,
        $usedWidth,
        $usedLength,
        $usedDepth
    ) {
        $this->box = $box;
        $this->items = $itemList;
        $this->remainingWidth = $remainingWidth;
        $this->remainingLength = $remainingLength;
        $this->remainingDepth = $remainingDepth;
        $this->remainingWeight = $remainingWeight;
        $this->usedWidth = $usedWidth;
        $this->usedLength = $usedLength;
        $this->usedDepth = $usedDepth;
    }

    /**
     * The constructor from v3.
     *
     * @param Box            $box
     * @param PackedItemList $packedItems
     *
     * @return self
     */
    public static function fromPackedItemList(Box $box, PackedItemList $packedItems)
    {
        $maxWidth = $maxLength = $maxDepth = $weight = 0;
        /** @var PackedItem $item */
        foreach (clone $packedItems as $item) {
            $maxWidth = max($maxWidth, $item->getX() + $item->getWidth());
            $maxLength = max($maxLength, $item->getY() + $item->getLength());
            $maxDepth = max($maxDepth, $item->getZ() + $item->getDepth());
            $weight += $item->getItem()->getWeight();
        }

        $packedBox = new self(
            $box,
            $packedItems->asItemList(),
            $box->getInnerWidth() - $maxWidth,
            $box->getInnerLength() - $maxLength,
            $box->getInnerDepth() - $maxDepth,
            $box->getMaxWeight() - $box->getEmptyWeight() - $weight,
            $maxWidth,
            $maxLength,
            $maxDepth
        );

        return $packedBox;
    }
}
