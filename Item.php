<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
namespace DVDoug\BoxPacker;

/**
 * An item to be packed
 * @author Doug Wright
 * @package BoxPacker
 */
interface Item
{

    /**
     * Item SKU etc
     * @return string
     */
    public function getDescription();

    /**
     * Item width in mm
     * @return int
     */
    public function getWidth();

    /**
     * Item length in mm
     * @return int
     */
    public function getLength();

    /**
     * Item depth in mm
     * @return int
     */
    public function getDepth();

    /**
     * Item weight in g
     * @return int
     */
    public function getWeight();

    /**
     * Item volume in mm^3
     * @return int
     */
    public function getVolume();

    /**
     * Does this item need to be kept flat?
     * XXX not yet used, all items are kept flat
     * @return bool
     */
    public function getKeepFlat();

}

