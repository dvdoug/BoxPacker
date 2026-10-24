<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker\Playground;

use DVDoug\BoxPacker\Item;
use DVDoug\BoxPacker\Rotation;

/**
 * Item described by the documentation playground.
 */
class PlaygroundItem implements Item
{
    public function __construct(
        private readonly string $description,
        private readonly int $width,
        private readonly int $length,
        private readonly int $depth,
        private readonly int $weight,
        private readonly Rotation $allowedRotation,
    ) {
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function getAllowedRotation(): Rotation
    {
        return $this->allowedRotation;
    }
}
