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
    public $reference;

    /**
     * Item width in mm
     * @var int
     */
    public $width;

    /**
     * Item length in mm
     * @var int
     */
    public $length;

    /**
     * Item depth in mm
     * @var int
     */
    public $depth;

    /**
     * Item weight in g
     * @var int
     */
    public $weight;

  }
