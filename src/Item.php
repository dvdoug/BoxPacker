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
     * Does this item need to be kept flat / packed "this way up"?
     */
    public function getKeepFlat(): bool;
}
