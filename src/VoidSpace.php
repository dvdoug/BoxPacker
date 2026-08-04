<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use JsonSerializable;

/**
 * @internal
 */
readonly class VoidSpace implements JsonSerializable
{
    public function __construct(
        public int $x,
        public int $y,
        public int $z,
        public int $width,
        public int $length,
        public int $depth,
    ) {
    }

    public function getVolume(): int
    {
        return $this->width * $this->length * $this->depth;
    }

    /**
     * Footprint area in the XY plane (mm²).
     */
    public function getFootprint(): int
    {
        return $this->width * $this->length;
    }

    public function jsonSerialize(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
            'z' => $this->z,
            'width' => $this->width,
            'length' => $this->length,
            'depth' => $this->depth,
        ];
    }
}
