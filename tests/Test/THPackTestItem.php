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
use DVDoug\BoxPacker\PackedItemList;

class THPackTestItem implements ConstrainedPlacementItem
{
    /**
     * @var string
     */
    private $description;

    /**
     * @var int
     */
    private $width;

    /**
     * @var int
     */
    private $length;

    /**
     * @var int
     */
    private $depth;

    /**
     * @var int
     */
    private $weight;

    /**
     * @var bool
     */
    private $widthAllowedVertical;

    /**
     * @var bool
     */
    private $lengthAllowedVertical;

    /**
     * @var bool
     */
    private $depthAllowedVertical;

    /**
     * TestItem constructor.
     */
    public function __construct(
        string $description,
        int $width,
        bool $widthAllowedVertical,
        int $length,
        bool $lengthAllowedVertical,
        int $depth,
        bool $depthAllowedVertical)
    {
        $this->description = $description;
        $this->width = $width;
        $this->widthAllowedVertical = $widthAllowedVertical;
        $this->length = $length;
        $this->lengthAllowedVertical = $lengthAllowedVertical;
        $this->depth = $depth;
        $this->depthAllowedVertical = $depthAllowedVertical;
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

    public function getKeepFlat(): bool
    {
        return !$this->widthAllowedVertical && !$this->lengthAllowedVertical && $this->depthAllowedVertical;
    }

    /**
     * Hook for user implementation of item-specific constraints, e.g. max <x> batteries per box.
     */
    public function canBePacked(
        Box $box,
        PackedItemList $alreadyPackedItems,
        int $proposedX,
        int $proposedY,
        int $proposedZ,
        int $width,
        int $length,
        int $depth
    ): bool {
        if ($depth === $this->width) {
            return $this->widthAllowedVertical;
        }
        if ($depth === $this->length) {
            return $this->lengthAllowedVertical;
        }
        if ($depth === $this->depth) {
            return $this->depthAllowedVertical;
        }
    }
}
