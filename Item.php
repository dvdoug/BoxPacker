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
  interface Item {

    /**
     * Item SKU etc
     * @var string
     */
    public function getDescription();

    /**
     * Item width in mm
     * @var int
     */
    public function getWidth();

    /**
     * Item length in mm
     * @var int
     */
    public function getLength();

    /**
     * Item depth in mm
     * @var int
     */
    public function getDepth();

    /**
     * Item weight in g
     * @var int
     */
    public function getWeight();

  }
