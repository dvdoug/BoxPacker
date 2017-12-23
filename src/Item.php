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
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Item width in mm.
     *
     * @return int
     */
    public function getWidth(): int;

    /**
     * Item length in mm.
     *
     * @return int
     */
    public function getLength(): int;

    /**
     * Item depth in mm.
     *
     * @return int
     */
    public function getDepth(): int;

    /**
     * Item weight in g.
     *
     * @return int
     */
    public function getWeight(): int;

    /**
     * Does this item need to be kept flat / packed "this way up"?
     *
     * @return bool
     */
    public function getKeepFlat(): bool;
}
