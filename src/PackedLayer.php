<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

/**
 * A packed layer.
 *
 * @author Doug Wright
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
     *
     * @param PackedItem $packedItem
     */
    public function insert(PackedItem $packedItem)
    {
        $this->items[] = $packedItem;
    }

    /**
     * Get the packed items.
     *
     * @return PackedItem[]
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Calculate footprint area of this layer.
     *
     * @return int mm^2
     */
    public function getFootprint()
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
    public function getStartDepth()
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
    public function getDepth()
    {
        $layerDepth = 0;

        foreach ($this->items as $item) {
            $layerDepth = max($layerDepth, $item->getZ() + $item->getDepth());
        }

        return $layerDepth - $this->getStartDepth();
    }
}
