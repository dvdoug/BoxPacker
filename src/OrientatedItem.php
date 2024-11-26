<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use JsonSerializable;
use Stringable;

use function atan;
use function min;
use function sort;

/**
 * An item to be packed.
 */
class OrientatedItem implements JsonSerializable, Stringable
{
    public readonly int $surfaceFootprint;

    /**
     * @var array<string, bool>
     */
    protected static array $stabilityCache = [];

    /**
     * @var int[]
     */
    protected array $dimensionsAsArray;

    public function __construct(
        public readonly Item $item,
        public readonly int $width,
        public readonly int $length,
        public readonly int $depth
    ) {
        $this->surfaceFootprint = $width * $length;

        $this->dimensionsAsArray = [$width, $length, $depth];
        sort($this->dimensionsAsArray);
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

    public function jsonSerialize(): array
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
