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

use function atan;
use function min;
use function sort;

/**
 * An item to be packed.
 *
 * @author Doug Wright
 */
class OrientatedItem implements JsonSerializable
{
    /**
     * @var Item
     */
    protected $item;

    /**
     * @var int
     */
    protected $width;

    /**
     * @var int
     */
    protected $length;

    /**
     * @var int
     */
    protected $depth;

    /**
     * @var int
     */
    protected $surfaceFootprint;

    /**
     * @var bool[]
     */
    protected static $stabilityCache = [];

    /**
     * @var array
     */
    protected $dimensionsAsArray;

    /**
     * Constructor.
     */
    public function __construct(Item $item, int $width, int $length, int $depth)
    {
        $this->item = $item;
        $this->width = $width;
        $this->length = $length;
        $this->depth = $depth;
        $this->surfaceFootprint = $width * $length;

        $this->dimensionsAsArray = [$width, $length, $depth];
        sort($this->dimensionsAsArray);
    }

    /**
     * Item.
     */
    public function getItem(): Item
    {
        return $this->item;
    }

    /**
     * Item width in mm in it's packed orientation.
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Item length in mm in it's packed orientation.
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * Item depth in mm in it's packed orientation.
     */
    public function getDepth(): int
    {
        return $this->depth;
    }

    /**
     * Calculate the surface footprint of the current orientation.
     */
    public function getSurfaceFootprint(): int
    {
        return $this->surfaceFootprint;
    }

    /**
     * Is this item stable (low centre of gravity), calculated as if the tipping point is >15 degrees.
     *
     * N.B. Assumes equal weight distribution.
     */
    public function isStable(): bool
    {
        $cacheKey = $this->width . '|' . $this->length . '|' . $this->depth;

        return static::$stabilityCache[$cacheKey] ?? (static::$stabilityCache[$cacheKey] = atan(min($this->length, $this->width) / ($this->depth ?: 1)) > 0.261);
    }

    /**
     * Is the supplied item the same size as this one?
     *
     * @internal
     */
    public function isSameDimensions(Item $item): bool
    {
        if ($item === $this->item) {
            return true;
        }

        $itemDimensions = [$item->getWidth(), $item->getLength(), $item->getDepth()];
        sort($itemDimensions);

        return $this->dimensionsAsArray === $itemDimensions;
    }

    #[ReturnTypeWillChange]
    public function jsonSerialize()/* : mixed */
    {
        return [
            'item' => $this->item,
            'width' => $this->width,
            'length' => $this->length,
            'depth' => $this->depth,
        ];
    }

    public function __toString(): string
    {
        return $this->width . '|' . $this->length . '|' . $this->depth;
    }
}
