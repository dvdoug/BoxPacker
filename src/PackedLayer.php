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

use const PHP_INT_MAX;

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
    private $startX = PHP_INT_MAX;

    /**
     * @var int
     */
    private $endX = 0;

    /**
     * @var int
     */
    private $startY = PHP_INT_MAX;

    /**
     * @var int
     */
    private $endY = 0;

    /**
     * @var int
     */
    private $startZ = PHP_INT_MAX;

    /**
     * @var int
     */
    private $endZ = 0;

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
     */
    public function insert(PackedItem $packedItem): void
    {
        $this->items[] = $packedItem;
        $this->weight += $packedItem->getItem()->getWeight();
        $this->startX = min($this->startX, $packedItem->getX());
        $this->endX = max($this->endX, $packedItem->getX() + $packedItem->getWidth());
        $this->startY = min($this->startY, $packedItem->getY());
        $this->endY = max($this->endY, $packedItem->getY() + $packedItem->getLength());
        $this->startZ = min($this->startZ, $packedItem->getZ());
        $this->endZ = max($this->endZ, $packedItem->getZ() + $packedItem->getDepth());
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
        return $this->getWidth() * $this->getLength();
    }

    public function getStartX(): int
    {
        return $this->startX;
    }

    public function getEndX(): int
    {
        return $this->endX;
    }

    public function getWidth(): int
    {
        return $this->endX ? $this->endX - $this->startX : 0;
    }

    public function getStartY(): int
    {
        return $this->startY;
    }

    public function getEndY(): int
    {
        return $this->endY;
    }

    public function getLength(): int
    {
        return $this->endY ? $this->endY - $this->startY : 0;
    }

    public function getStartZ(): int
    {
        return $this->startZ;
    }

    public function getEndZ(): int
    {
        return $this->endZ;
    }

    public function getDepth(): int
    {
        return $this->endZ ? $this->endZ - $this->startZ : 0;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function merge(self $otherLayer): void
    {
        foreach ($otherLayer->items as $packedItem) {
            $this->insert($packedItem);
        }
    }
}
