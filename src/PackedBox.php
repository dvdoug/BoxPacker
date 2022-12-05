<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use JsonSerializable;
use ReturnTypeWillChange;

use function iterator_to_array;
use function max;
use function round;
use function array_merge;
use function is_array;

/**
 * A "box" with items.
 *
 * @author Doug Wright
 */
class PackedBox implements JsonSerializable
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
     * Volume used for items as % of box.
     *
     * @var float
     */
    protected $volumeUtilisation;

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
        if ($this->itemWeight !== null) {
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

        /** @var PackedItem $item */
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

        /** @var PackedItem $item */
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

        /** @var PackedItem $item */
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
     * Constructor.
     */
    public function __construct(Box $box, PackedItemList $packedItemList)
    {
        $this->box = $box;
        $this->items = $packedItemList;

        $this->volumeUtilisation = round($this->getUsedVolume() / ($this->getInnerVolume() ?: 1) * 100, 1);
    }

    #[ReturnTypeWillChange]
    public function jsonSerialize()/* : mixed */
    {
        $userValues = [];

        if ($this->box instanceof JsonSerializable) {
            $userSerialisation = $this->box->jsonSerialize();
            if (is_array($userSerialisation)) {
                $userValues = $userSerialisation;
            } else {
                $userValues = ['extra' => $userSerialisation];
            }
        }

        return [
            'box' => array_merge(
                $userValues,
                [
                    'reference' => $this->box->getReference(),
                    'innerWidth' => $this->box->getInnerWidth(),
                    'innerLength' => $this->box->getInnerLength(),
                    'innerDepth' => $this->box->getInnerDepth(),
                ]
            ),
            'items' => iterator_to_array($this->items),
        ];
    }
}
