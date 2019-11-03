<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use function max;
use function min;

/**
 * A packed layer.
 *
 * @author Doug Wright
 * @internal
 */
class PackedLayer
{
    /**
     * Items packed into this layer.
     *
     * @var PackedItem[]
     */
    protected $items = [];

    /**
     * Add a packed item to this layer.
     */
    public function insert(PackedItem $packedItem): void
    {
        $this->items[] = $packedItem;
    }

    /**
     * Get the packed items.
     *
     * @return PackedItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Calculate footprint area of this layer.
     *
     * @return int mm^2
     */
    public function getFootprint(): int
    {
        $layerWidth = 0;
        $layerLength = 0;

        foreach ($this->items as $item) {
            $layerWidth = max($layerWidth, $item->getX() + $item->getWidth());
            $layerLength = max($layerLength, $item->getY() + $item->getLength());
        }

        return $layerWidth * $layerLength;
    }

    /**
     * Calculate start depth of this layer.
     *
     * @return int mm
     */
    public function getStartDepth(): int
    {
        $startDepth = PHP_INT_MAX;

        foreach ($this->items as $item) {
            $startDepth = min($startDepth, $item->getZ());
        }

        return $startDepth;
    }

    /**
     * Calculate depth of this layer.
     *
     * @return int mm
     */
    public function getDepth(): int
    {
        $layerDepth = 0;

        foreach ($this->items as $item) {
            $layerDepth = max($layerDepth, $item->getZ() + $item->getDepth());
        }

        return $layerDepth - $this->getStartDepth();
    }
}
