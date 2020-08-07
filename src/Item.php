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
 *
 * @author Doug Wright
 */
interface Item
{
    public const ROTATION_NEVER = 1;

    public const ROTATION_KEEP_FLAT = 2;

    public const ROTATION_BEST_FIT = 6;

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
     * Possible item rotations allowed. One of the ROTATION_* constants.
     */
    public function getAllowedRotations(): int;
}
