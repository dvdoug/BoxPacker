<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
  namespace DVDoug\BoxPacker;

  /**
   * List of items to be packed, ordered by volume
   * @author Doug Wright
   * @package BoxPacker
   */
  class ItemList extends \SplMaxHeap {

    /**
     * Compare elements in order to place them correctly in the heap while sifting up.
     * @see \SplMaxHeap::compare()
     */
    public function compare($aItemA, $aItemB) {
      return $aItemA->getVolume() - $aItemB->getVolume();
    }

  }
