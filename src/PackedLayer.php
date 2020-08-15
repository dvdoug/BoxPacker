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
 * @internal
 */
class PackedLayer
{
    /**
     * @var int
     */
    private $startDepth = PHP_INT_MAX;

    /**
     * @var int
     */
    private $endDepth = 0;

    /**
     * @var int
     */
    private $weight = 0;
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
        $this->weight += $packedItem->getItem()->getWeight();
        $this->startDepth = min($this->startDepth, $packedItem->getZ());
        $this->endDepth = max($this->endDepth, $packedItem->getZ() + $packedItem->getDepth());
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
        return $this->startDepth;
    }

    /**
     * Calculate depth of this layer.
     *
     * @return int mm
     */
    public function getDepth()
    {
        return $this->endDepth - $this->getStartDepth();
    }

    /**
     * Calculate weight of this layer.
     *
     * @return int weight in grams
     */
    public function getWeight()
    {
        return $this->weight;
    }
}
