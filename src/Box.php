<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
declare(strict_types=1);
namespace DVDoug\BoxPacker;

/**
 * A "box" (or envelope?) to pack items into
 * @author Doug Wright
 * @package BoxPacker
 */
interface Box
{

    /**
     * Reference for box type (e.g. SKU or description)
     * @return string
     */
    public function getReference(): string;

    /**
     * Outer width in mm
     * @return int
     */
    public function getOuterWidth(): int;

    /**
     * Outer length in mm
     * @return int
     */
    public function getOuterLength(): int;

    /**
     * Outer depth in mm
     * @return int
     */
    public function getOuterDepth(): int;

    /**
     * Empty weight in g
     * @return int
     */
    public function getEmptyWeight(): int;

    /**
     * Inner width in mm
     * @return int
     */
    public function getInnerWidth(): int;

    /**
     * Inner length in mm
     * @return int
     */
    public function getInnerLength(): int;

    /**
     * Inner depth in mm
     * @return int
     */
    public function getInnerDepth(): int;

    /**
     * Max weight the packaging can hold in g
     * @return int
     */
    public function getMaxWeight(): int;
}
