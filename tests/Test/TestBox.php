<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker\Test;

use DVDoug\BoxPacker\Box;
use JsonSerializable;

class TestBox implements Box, JsonSerializable
{
    /**
     * @var string
     */
    private $reference;

    /**
     * @var string
     */
    private $type;

    /**
     * @var int
     */
    private $outerWidth;

    /**
     * @var int
     */
    private $outerLength;

    /**
     * @var int
     */
    private $outerDepth;

    /**
     * @var int
     */
    private $emptyWeight;

    /**
     * @var int
     */
    private $innerWidth;

    /**
     * @var int
     */
    private $innerLength;

    /**
     * @var int
     */
    private $innerDepth;

    /**
     * @var int
     */
    private $maxWeight;

    /**
     * TestBox constructor.
     */
    public function __construct(
        string $reference,
        string $type,
        int $outerWidth,
        int $outerLength,
        int $outerDepth,
        int $emptyWeight,
        int $innerWidth,
        int $innerLength,
        int $innerDepth,
        int $maxWeight
    ) {
        $this->reference = $reference;
        $this->type = $type;
        if($type !== 'FlatBag'){
            $this->outerWidth = $outerWidth;
            $this->outerLength = $outerLength;
            $this->outerDepth = $outerDepth;
            $this->emptyWeight = $emptyWeight;
            $this->innerWidth = $innerWidth;
            $this->innerLength = $innerLength;
            $this->innerDepth = $innerDepth;
            $this->maxWeight = $maxWeight;
        }else{
            $this->outerWidth = $outerWidth;
            $this->outerLength = 0;
            $this->outerDepth = $outerDepth;
            $this->emptyWeight = $emptyWeight;
            $this->innerWidth = 0;
            $this->innerLength = 0;
            $this->innerDepth = 0;
            $this->maxWeight = $maxWeight;
        }
        
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getOuterWidth(): int
    {
        return $this->outerWidth;
    }

    public function getOuterLength(): int
    {
        return $this->outerLength;
    }

    public function getOuterDepth(): int
    {
        return $this->outerDepth;
    }

    public function getEmptyWeight(): int
    {
        return $this->emptyWeight;
    }

    public function getInnerWidth(): int
    {
        return $this->innerWidth;
    }

    public function getInnerLength(): int
    {
        return $this->innerLength;
    }

    public function getInnerDepth(): int
    {
        return $this->innerDepth;
    }

    public function setFlatBagDimensions($innerWidth, $innerLength, $innerDepth): bool
    {
        $this->innerWidth = $innerWidth;
        $this->innerLength = $innerLength;
        $this->innerDepth = $innerDepth;
        return true;
    }

    public function setInnerDepth($innerDepth): bool
    {
        $this->innerDepth = $innerDepth;
        return true;
    }

    public function getMaxWeight(): int
    {
        return $this->maxWeight;
    }

    public function getMaxVolume(): int
    {
        if($this->type == 'FlatBag'){
            return (int)round($this->outerWidth * 0.65 * $this->outerWidth * 0.25 *  ($this->outerDepth - $this->outerWidth * 0.35));
        }
        return  $this->innerWidth * $this->innerLength * $this->innerDepth;
    }

    public function jsonSerialize(): array
    {
        return [
            'reference' => $this->reference,
            'type' => $this->type,
            'innerWidth' => $this->innerWidth,
            'innerLength' => $this->innerLength,
            'innerDepth' => $this->innerDepth,
            'emptyWeight' => $this->emptyWeight,
            'maxWeight' => $this->maxWeight,
        ];
    }
}
