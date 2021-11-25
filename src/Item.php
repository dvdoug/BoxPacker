<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

/**
 * An item to be packed.
 */
interface Item
{
    /**
     * Item SKU etc.
     */
    public function getDescription(): string;

    /**
     * Item width in mm.
     */
    public function getWidth(): int;

    /**
     * Item length in mm.
     */
    public function getLength(): int;

    /**
     * Item depth in mm.
     */
    public function getDepth(): int;

    /**
     * Item weight in g.
     */
    public function getWeight(): int;

    /**
     * Possible item rotations allowed.
     */
    public function getAllowedRotation(): Rotation;
}
