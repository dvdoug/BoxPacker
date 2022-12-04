<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker\Test;

use DVDoug\BoxPacker\Box;
use DVDoug\BoxPacker\ConstrainedPlacementItem;
use DVDoug\BoxPacker\PackedBox;
use DVDoug\BoxPacker\Rotation;

class THPackTestItem implements ConstrainedPlacementItem
{
    /**
     * TestItem constructor.
     */
    public function __construct(
        private readonly string $description,
        private readonly int $width,
        private readonly bool $widthAllowedVertical,
        private readonly int $length,
        private readonly bool $lengthAllowedVertical,
        private readonly int $depth,
        private readonly bool $depthAllowedVertical
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
        return (!$this->widthAllowedVertical && !$this->lengthAllowedVertical && $this->depthAllowedVertical) ? Rotation::KeepFlat : Rotation::BestFit;
    }

    /**
     * Hook for user implementation of item-specific constraints, e.g. max <x> batteries per box.
     */
    public function canBePacked(
        PackedBox $packedBox,
        int $proposedX,
        int $proposedY,
        int $proposedZ,
        int $width,
        int $length,
        int $depth
    ): bool {
        $ok = false;
        if ($depth === $this->width) {
            $ok = $ok || $this->widthAllowedVertical;
        }
        if ($depth === $this->length) {
            $ok = $ok || $this->lengthAllowedVertical;
        }
        if ($depth === $this->depth) {
            $ok = $ok || $this->depthAllowedVertical;
        }

        return $ok;
    }
}
