<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker\Test;

use DVDoug\BoxPacker\Item;
use DVDoug\BoxPacker\Rotation;

/**
 * Plain OR-library item (no placement hook).
 *
 * Vertical restrictions that reduce to {@see Rotation::KeepFlat} or unrestricted
 * {@see Rotation::BestFit} are expressed here via dimensions + rotation only.
 */
class THPackTestItem implements Item
{
    public function __construct(
        private readonly string $description,
        private readonly int $width,
        private readonly int $length,
        private readonly int $depth,
        private readonly Rotation $allowedRotation = Rotation::BestFit
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
        return 0;
    }

    public function getAllowedRotation(): Rotation
    {
        return $this->allowedRotation;
    }
}
