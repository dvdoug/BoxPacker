<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
  namespace DVDoug\BoxPacker;

  define('MAX_32BIT_INT', pow(2,31) - 1);

  /**
   * List of boxes available to put items into, ordered by volume
   * @author Doug Wright
   * @package BoxPacker
   */
  class BoxList extends \SplMinHeap {
    private $min =  PHP_INT_MAX;
    private $max =  0;

    public function insert($value) {
      $this->min = min($this->min, $value->getInnerVolume());
      $this->max = max($this->max, $value->getInnerVolume());
      parent::insert($value);
    }

    /**
     * Compare elements in order to place them correctly in the heap while sifting up.
     * @see \SplMinHeap::compare()
     */
    public function compare($aBoxA, $aBoxB) {
      // Scale our result compared to our max and min volumes to never have integer overflow issues
      return floor((($aBoxB->getInnerVolume() - $aBoxA->getInnerVolume()) / ($this->max - $this->min)) * MAX_32BIT_INT);
    }

  }
