<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

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
     */
    public function getReference(): string;

    /**
     * Outer width in mm.
     */
    public function getOuterWidth(): int;

    /**
     * Outer length in mm.
     */
    public function getOuterLength(): int;

    /**
     * Outer depth in mm.
     */
    public function getOuterDepth(): int;

    /**
     * Empty weight in g.
     */
    public function getEmptyWeight(): int;

    /**
     * Inner width in mm.
     */
    public function getInnerWidth(): int;

    /**
     * Inner length in mm.
     */
    public function getInnerLength(): int;

    /**
     * Inner depth in mm.
     */
    public function getInnerDepth(): int;

    /**
     * Max weight the packaging can hold in g.
     */
    public function getMaxWeight(): int;
}
