<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

/**
 * A "box" (or envelope?) to pack items into with limited supply.
 *
 * @author Doug Wright
 */
interface LimitedSupplyBox extends Box
{
    /**
     * Quantity of boxes available.
     */
    public function getQuantityAvailable(): int;
}
