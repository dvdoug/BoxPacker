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
 * OR-library item where a non-trivial subset of edges may be used as the vertical axis.
 *
 * Use only when the restriction cannot be expressed as {@see Rotation::KeepFlat} or free BestFit
 * (i.e. exactly two of the three edges may stand vertical).
 */
class THPackConstrainedTestItem extends THPackTestItem implements ConstrainedPlacementItem
{
    public function __construct(
        string $description,
        int $width,
        private readonly bool $widthAllowedVertical,
        int $length,
        private readonly bool $lengthAllowedVertical,
        int $depth,
        private readonly bool $depthAllowedVertical
    ) {
        parent::__construct($description, $width, $length, $depth, Rotation::BestFit);
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
        // Placement depth must be an edge the dataset allows as vertical
        return ($depth === $this->getWidth() && $this->widthAllowedVertical)
            || ($depth === $this->getLength() && $this->lengthAllowedVertical)
            || ($depth === $this->getDepth() && $this->depthAllowedVertical);
    }
}
