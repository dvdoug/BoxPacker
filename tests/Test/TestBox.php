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
    private mixed $jsonSerializeOverride = null;

    /**
     * TestBox constructor.
     */
    public function __construct(
        private readonly string $reference,
        private readonly float $outerWidth,
        private readonly float $outerLength,
        private readonly float $outerDepth,
        private readonly float $emptyWeight,
        private readonly float $innerWidth,
        private readonly float $innerLength,
        private readonly float $innerDepth,
        private readonly float $maxWeight
    ) {
    }

    public function getReference(): string
    {
        return $this->reference;
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

    public function getMaxWeight(): int
    {
        return $this->maxWeight;
    }

    public function jsonSerialize(): mixed
    {
        if (isset($this->jsonSerializeOverride)) {
            return $this->jsonSerializeOverride;
        }

        return [
            'reference' => $this->reference,
            'innerWidth' => $this->innerWidth,
            'innerLength' => $this->innerLength,
            'innerDepth' => $this->innerDepth,
            'emptyWeight' => $this->emptyWeight,
            'maxWeight' => $this->maxWeight,
        ];
    }

    public function setJsonSerializeOverride(mixed $override): void
    {
        $this->jsonSerializeOverride = $override;
    }
}
