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
 * Class WorkingVolume.
 * @internal
 */
class WorkingVolume implements Box, JsonSerializable
{
    private int $width;

    private int $length;

    private int $depth;

    private int $maxWeight;

    public function __construct(
        int $width,
        int $length,
        int $depth,
        int $maxWeight
    ) {
        $this->width = $width;
        $this->length = $length;
        $this->depth = $depth;
        $this->maxWeight = $maxWeight;
    }

    public function getReference(): string
    {
        return "Working Volume {$this->width}x{$this->length}x{$this->depth}";
    }

    public function getType(): string
    {
        return "";
    }

    public function getOuterWidth(): int
    {
        return $this->width;
    }

    public function getOuterLength(): int
    {
        return $this->length;
    }

    public function getOuterDepth(): int
    {
        return $this->depth;
    }

    public function getEmptyWeight(): int
    {
        return 0;
    }

    public function getInnerWidth(): int
    {
        return $this->width;
    }

    public function getInnerLength(): int
    {
        return $this->length;
    }

    public function getInnerDepth(): int
    {
        return $this->depth;
    }

    public function getMaxWeight(): int
    {
        return $this->maxWeight;
    }

    public function setFlatBagDimensions($boxWidth, $boxLength, $boxDepth): bool
    {
        return true;
    }

    public function getMaxVolume(): int
    {
       return 0; 
    }

    public function setInnerDepth($depth): bool
    {
        return $this->innerDepth = $depth;
    }

    public function jsonSerialize(): array
    {
        return [
            'reference' => $this->getReference(),
            'type' => $this->getType(),
            'width' => $this->width,
            'length' => $this->length,
            'depth' => $this->depth,
            'maxWeight' => $this->maxWeight,
        ];
    }
}
