<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

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
    public function getDescription();

    /**
     * Item width in mm.
     *
     * @return int
     */
    public function getWidth();

    /**
     * Item length in mm.
     *
     * @return int
     */
    public function getLength();

    /**
     * Item depth in mm.
     *
     * @return int
     */
    public function getDepth();

    /**
     * Item weight in g.
     *
     * @return int
     */
    public function getWeight();

    /**
     * Item volume in mm^3.
     *
     * @return int
     */
    public function getVolume();

    /**
     * Does this item need to be kept flat / packed "this way up"?
     *
     * @return bool
     */
    public function getKeepFlat();
}
