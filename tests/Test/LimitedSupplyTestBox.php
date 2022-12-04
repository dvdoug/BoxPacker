<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker\Test;

use DVDoug\BoxPacker\LimitedSupplyBox;

class LimitedSupplyTestBox extends TestBox implements LimitedSupplyBox
{
    public function __construct(
        string $reference,
        int $outerWidth,
        int $outerLength,
        int $outerDepth,
        int $emptyWeight,
        int $innerWidth,
        int $innerLength,
        int $innerDepth,
        int $maxWeight,
        private readonly int $quantity
    ) {
        parent::__construct($reference, $outerWidth, $outerLength, $outerDepth, $emptyWeight, $innerWidth, $innerLength, $innerDepth, $maxWeight);
    }

    public function getQuantityAvailable(): int
    {
        return $this->quantity;
    }
}
