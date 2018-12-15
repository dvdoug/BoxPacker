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
     *
     * @param int $width
     * @param int $length
     * @param int $depth
     * @param int $maxWeight
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

    /**
     * @return string
     */
    public function getReference(): string
    {
        return 'Working Volume';
    }

    /**
     * @return int
     */
    public function getOuterWidth(): int
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getOuterLength(): int
    {
        return $this->length;
    }

    /**
     * @return int
     */
    public function getOuterDepth(): int
    {
        return $this->depth;
    }

    /**
     * @return int
     */
    public function getEmptyWeight(): int
    {
        return 0;
    }

    /**
     * @return int
     */
    public function getInnerWidth(): int
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getInnerLength(): int
    {
        return $this->length;
    }

    /**
     * @return int
     */
    public function getInnerDepth(): int
    {
        return $this->depth;
    }

    /**
     * @return int
     */
    public function getMaxWeight(): int
    {
        return $this->maxWeight;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize(): array
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
