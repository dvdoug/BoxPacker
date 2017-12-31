<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

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
}
