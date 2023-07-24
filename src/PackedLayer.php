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
 * @internal
 */
class PackedLayer
{
    /**
     * @var PackedItem[]
     */
    protected array $items = [];

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
        return $this->getWidth() * $this->getLength();
    }

    public function getStartX(): int
    {
        if (!$this->items) {
            return 0;
        }

        $values = [];
        foreach ($this->items as $item) {
            $values[] = $item->getX();
        }

        return min($values);
    }

    public function getEndX(): int
    {
        if (!$this->items) {
            return 0;
        }

        $values = [];
        foreach ($this->items as $item) {
            $values[] = $item->getX() + $item->getWidth();
        }

        return max($values);
    }

    public function getWidth(): int
    {
        if (!$this->items) {
            return 0;
        }

        $start = [];
        $end = [];
        foreach ($this->items as $item) {
            $start[] = $item->getX();
            $end[] = $item->getX() + $item->getWidth();
        }

        return max($end) - min($start);
    }

    public function getStartY(): int
    {
        if (!$this->items) {
            return 0;
        }

        $values = [];
        foreach ($this->items as $item) {
            $values[] = $item->getY();
        }

        return min($values);
    }

    public function getEndY(): int
    {
        if (!$this->items) {
            return 0;
        }

        $values = [];
        foreach ($this->items as $item) {
            $values[] = $item->getY() + $item->getLength();
        }

        return max($values);
    }

    public function getLength(): int
    {
        if (!$this->items) {
            return 0;
        }

        $start = [];
        $end = [];
        foreach ($this->items as $item) {
            $start[] = $item->getY();
            $end[] = $item->getY() + $item->getLength();
        }

        return max($end) - min($start);
    }

    public function getStartZ(): int
    {
        if (!$this->items) {
            return 0;
        }

        $values = [];
        foreach ($this->items as $item) {
            $values[] = $item->getZ();
        }

        return min($values);
    }

    public function getEndZ(): int
    {
        if (!$this->items) {
            return 0;
        }

        $values = [];
        foreach ($this->items as $item) {
            $values[] = $item->getZ() + $item->getDepth();
        }

        return max($values);
    }

    public function getDepth(): int
    {
        if (!$this->items) {
            return 0;
        }

        $start = [];
        $end = [];
        foreach ($this->items as $item) {
            $start[] = $item->getZ();
            $end[] = $item->getZ() + $item->getDepth();
        }

        return max($end) - min($start);
    }

    public function getWeight(): int
    {
        $weight = 0;
        foreach ($this->items as $item) {
            $weight += $item->getItem()->getWeight();
        }

        return $weight;
    }

    public function merge(self $otherLayer): void
    {
        foreach ($otherLayer->items as $packedItem) {
            $this->items[] = $packedItem;
        }
    }
}
