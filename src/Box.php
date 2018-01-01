<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

/**
 * A "box" (or envelope?) to pack items into.
 *
 * @author Doug Wright
 */
interface Box
{
    /**
     * Reference for box type (e.g. SKU or description).
     *
     * @return string
     */
    public function getReference();

    /**
     * Outer width in mm.
     *
     * @return int
     */
    public function getOuterWidth();

    /**
     * Outer length in mm.
     *
     * @return int
     */
    public function getOuterLength();

    /**
     * Outer depth in mm.
     *
     * @return int
     */
    public function getOuterDepth();

    /**
     * Empty weight in g.
     *
     * @return int
     */
    public function getEmptyWeight();

    /**
     * Inner width in mm.
     *
     * @return int
     */
    public function getInnerWidth();

    /**
     * Inner length in mm.
     *
     * @return int
     */
    public function getInnerLength();

    /**
     * Inner depth in mm.
     *
     * @return int
     */
    public function getInnerDepth();

    /**
     * Total inner volume of packing in mm^3.
     *
     * @return int
     */
    public function getInnerVolume();

    /**
     * Max weight the packaging can hold in g.
     *
     * @return int
     */
    public function getMaxWeight();
}
