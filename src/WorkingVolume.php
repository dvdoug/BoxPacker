<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
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
        $width,
        $length,
        $depth,
        $maxWeight
    ) {
        $this->width = $width;
        $this->length = $length;
        $this->depth = $depth;
        $this->maxWeight = $maxWeight;
    }

    /**
     * @return string
     */
    public function getReference()
    {
        return "Working Volume {$this->width}x{$this->length}x{$this->depth}";
    }

    /**
     * @return int
     */
    public function getOuterWidth()
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getOuterLength()
    {
        return $this->length;
    }

    /**
     * @return int
     */
    public function getOuterDepth()
    {
        return $this->depth;
    }

    /**
     * @return int
     */
    public function getEmptyWeight()
    {
        return 0;
    }

    /**
     * @return int
     */
    public function getInnerWidth()
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getInnerLength()
    {
        return $this->length;
    }

    /**
     * @return int
     */
    public function getInnerDepth()
    {
        return $this->depth;
    }

    /**
     * @return int
     */
    public function getMaxWeight()
    {
        return $this->maxWeight;
    }

    /**
     * @return int
     */
    public function getInnerVolume()
    {
        return $this->width * $this->length * $this->depth;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
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
