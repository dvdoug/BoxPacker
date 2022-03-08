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

/**
 * Class WorkingVolume.
 * @internal
 */
class WorkingVolume implements Box, JsonSerializable
{
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
    private $maxWeight;

    /**
     * Constructor.
     */
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

    #[ReturnTypeWillChange]
    public function jsonSerialize()/* : mixed */
    {
        return [
            'reference' => $this->getReference(),
            'width' => $this->width,
            'length' => $this->length,
            'depth' => $this->depth,
            'maxWeight' => $this->maxWeight,
        ];
    }
}
