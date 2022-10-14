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

/**
 * A "box" with items.
 */
class PackedBox implements JsonSerializable
{
    protected Box $box;

    protected PackedItemList $items;

    protected int $itemWeight = 0;

    protected float $volumeUtilisation;

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
        if($this->box->getType() == 'FlatBag'){
            return (int)round($this->box->getOuterWidth() * 0.65 * $this->box->getOuterWidth() * 0.25 *  ($this->box->getOuterDepth() - $this->box->getOuterWidth() * 0.35));
        }
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

    public function __construct(Box $box, PackedItemList $packedItemList)
    {
        $this->box = $box;
        $this->items = $packedItemList;

        foreach ($this->items as $item) {
            $this->itemWeight += $item->getItem()->getWeight();
        }
        $this->volumeUtilisation = round($this->getUsedVolume() / ($this->getInnerVolume() ?: 1) * 100, 1);
    }

    public function jsonSerialize(): array
    {
        return [
            'box' => [
                'reference' => $this->box->getReference(),
                'innerWidth' => $this->box->getInnerWidth(),
                'innerLength' => $this->box->getInnerLength(),
                'innerDepth' => $this->box->getInnerDepth(),
            ],
            'items' => iterator_to_array($this->items),
        ];
    }
}
