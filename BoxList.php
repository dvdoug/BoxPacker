<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
  namespace DVDoug\BoxPacker;

  /**
   * List of boxes available to put items into, ordered by volume
   * @author Doug Wright
   * @package BoxPacker
   */
  class BoxList extends \SplMinHeap {

    /**
     * Compare elements in order to place them correctly in the heap while sifting up.
     * @see \SplMinHeap::compare()
     */
    public function compare($aBoxA, $aBoxB) {
      return $aBoxB->getInnerVolume() - $aBoxA->getInnerVolume();
    }

  }
