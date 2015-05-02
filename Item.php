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
     * @return string
     */
    public function getDescription();

    /**
     * @param $width
     */
    public function setWidth($width);

    /**
     * Item width in mm
     * @return int
     */
    public function getWidth();

    /**
     * @param $length
     */
    public function setLength($length);

    /**
     * Item length in mm
     * @return int
     */
    public function getLength();

    /**
     * @param $depth
     */
    public function setDepth($depth);

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

  }
