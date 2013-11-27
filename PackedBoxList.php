<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
  namespace DVDoug\BoxPacker;

  /**
   * List of possible packed box choices, ordered by utilisation (item count, volume)
   * @author Doug Wright
   * @package BoxPacker
   */
  class PackedBoxList extends \SplMinHeap {

    /**
     * Compare elements in order to place them correctly in the heap while sifting up.
     * @see \SplMinHeap::compare()
     */
    public function compare(PackedBox $aBoxA, PackedBox $aBoxB) {
      $choice = $aBoxA->getItems()->count() - $aBoxB->getItems()->count();
      if ($choice === 0) {
        $choice = $aBoxB->getBox()->getInnerVolume() - $aBoxA->getBox()->getInnerVolume();
      }
      return $choice;
    }

    /**
     * Calculate the variance in weight between these boxes
     * @return float
     */
    public function getWeightVariance() {
      $weights = [];
      $variance = 0;

      foreach (clone $this as $box) {
        $weights[] = $box->getWeight();
      }

      $mean = array_sum($weights) / count($weights);

      foreach ($weights as $weight) {
        $variance += pow($weight - $mean, 2);
      }

      return $variance / count($weights);

    }

  }
