<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker\Test;

use DVDoug\BoxPacker\ConstrainedPlacementItem;
use DVDoug\BoxPacker\PackedBox;
use DVDoug\BoxPacker\Rotation;

/**
 * OR-library style item: each edge may or may not be allowed as the vertical (depth) axis.
 */
class THPackTestItem implements ConstrainedPlacementItem
{
    private readonly Rotation $allowedRotation;

    public function __construct(
        private readonly string $description,
        private readonly int $width,
        private readonly bool $widthAllowedVertical,
        private readonly int $length,
        private readonly bool $lengthAllowedVertical,
        private readonly int $depth,
        private readonly bool $depthAllowedVertical
    ) {
        $this->allowedRotation = (
            !$widthAllowedVertical
            && !$lengthAllowedVertical
            && $depthAllowedVertical
        ) ? Rotation::KeepFlat : Rotation::BestFit;
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

    public function canBePacked(
        PackedBox $packedBox,
        int $proposedX,
        int $proposedY,
        int $proposedZ,
        int $width,
        int $length,
        int $depth
    ): bool {
        return ($depth === $this->width && $this->widthAllowedVertical)
            || ($depth === $this->length && $this->lengthAllowedVertical)
            || ($depth === $this->depth && $this->depthAllowedVertical);
    }
}
