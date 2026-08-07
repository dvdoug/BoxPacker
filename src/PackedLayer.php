<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use function count;

/**
 * A packed layer.
 * @internal
 */
class PackedLayer
{
    /**
     * @var PackedItem[]
     */
    public array $items = [];

    public int $startX = 0;

    public int $endX = 0;

    public int $startY = 0;

    public int $endY = 0;

    public int $startZ = 0;

    public int $endZ = 0;

    public int $width = 0;

    public int $length = 0;

    public int $depth = 0;

    public int $footprint = 0;

    public int $weight = 0;

    /**
     * Add a packed item to this layer.
     */
    public function insert(PackedItem $packedItem): void
    {
        $this->items[] = $packedItem;
        $this->weight += $packedItem->weight;

        $itemEndX = $packedItem->x + $packedItem->width;
        $itemEndY = $packedItem->y + $packedItem->length;
        $itemEndZ = $packedItem->z + $packedItem->depth;

        if (count($this->items) === 1) {
            $this->startX = $packedItem->x;
            $this->endX = $itemEndX;
            $this->startY = $packedItem->y;
            $this->endY = $itemEndY;
            $this->startZ = $packedItem->z;
            $this->endZ = $itemEndZ;
        } else {
            if ($packedItem->x < $this->startX) {
                $this->startX = $packedItem->x;
            }
            if ($itemEndX > $this->endX) {
                $this->endX = $itemEndX;
            }
            if ($packedItem->y < $this->startY) {
                $this->startY = $packedItem->y;
            }
            if ($itemEndY > $this->endY) {
                $this->endY = $itemEndY;
            }
            if ($packedItem->z < $this->startZ) {
                $this->startZ = $packedItem->z;
            }
            if ($itemEndZ > $this->endZ) {
                $this->endZ = $itemEndZ;
            }
        }

        $this->width = $this->endX - $this->startX;
        $this->length = $this->endY - $this->startY;
        $this->depth = $this->endZ - $this->startZ;
        $this->footprint = $this->width * $this->length;
    }

    public function merge(self $otherLayer): void
    {
        foreach ($otherLayer->items as $packedItem) {
            $this->insert($packedItem);
        }
    }
}
