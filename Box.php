<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
  namespace DVDoug\BoxPacker;

  /**
   * A "box" (or envelope?) to pack items into
   * @author Doug Wright
   * @package Publication
   */
  interface Box {

    /**
     * Reference for box type (e.g. SKU or description)
     * @return string
     */
    public function getReference();

    /**
     * Outer width in mm
     * @return int
     */
    public function getOuterWidth();

    /**
     * Outer length in mm
     * @return int
     */
    public function getOuterLength();

    /**
     * Outer depth in mm
     * @return int
     */
    public function getOuterDepth();

    /**
     * Empty weight in g
     * @return int
     */
    public function getEmptyWeight();

    /**
     * Inner width in mm
     * @return int
     */
    public function getInnerWidth();

    /**
     * Outer length in mm
     * @return int
     */
    public function getInnerLength();

    /**
     * Outer depth in mm
     * @return int
     */
    public function getInnerDepth();

    /**
     * Max weight the packaging can hold in g
     * @return int
     */
    public function getMaxWeight();

  }
