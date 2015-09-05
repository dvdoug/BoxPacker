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
     * Average (mean) weight of boxes
     * @var float
     */
    protected $meanWeight;

    /**
     * Variance in weight between boxes
     * @var float
     */
    protected $weightVariance;

    /**
     * Compare elements in order to place them correctly in the heap while sifting up.
     * @see \SplMinHeap::compare()
     */
    public function compare($aBoxA, $aBoxB) {
      $choice = $aBoxA->getItems()->count() - $aBoxB->getItems()->count();
      if ($choice === 0) {
        $choice = $aBoxB->getBox()->getInnerVolume() - $aBoxA->getBox()->getInnerVolume();
      }
      return $choice;
    }

    /**
     * Reversed version of compare
     * @return int
     */
    public function reverseCompare($aBoxA, $aBoxB) {
      $choice = $aBoxB->getItems()->count() - $aBoxA->getItems()->count();
      if ($choice === 0) {
        $choice = $aBoxA->getBox()->getInnerVolume() - $aBoxB->getBox()->getInnerVolume();
      }
      return $choice;
    }

    /**
     * Calculate the average (mean) weight of the boxes
     * @return float
     */
    public function getMeanWeight() {

      if (!is_null($this->meanWeight)) {
        return $this->meanWeight;
      }

      foreach (clone $this as $box) {
        $this->meanWeight += $box->getWeight();
      }

      return $this->meanWeight /= $this->count();

    }

    /**
     * Calculate the variance in weight between these boxes
     * @return float
     */
    public function getWeightVariance() {

      if (!is_null($this->weightVariance)) {
        return $this->weightVariance;
      }

      $mean = $this->getMeanWeight();

      foreach (clone $this as $box) {
        $this->weightVariance += pow($box->getWeight() - $mean, 2);
      }

      return $this->weightVariance /= $this->count();

    }

    /**
     * Get volume utilisation of the set of packed boxes
     * @return float
     */
    public function getVolumeUtilisation() {
      $itemVolume = 0;
      $boxVolume = 0;

      /** @var PackedBox $box */
      foreach (clone $this as $box) {
        $boxVolume += $box->getBox()->getInnerVolume();

        /** @var Item $item */
        foreach (clone $box->getItems() as $item ) {
          $itemVolume += $item->getVolume();
        }
      }

      return round($itemVolume / $boxVolume * 100, 1);
    }

    /**
     * Do a bulk insert
     * @param array $aBoxes
     */
    public function insertFromArray(array $aBoxes) {
      foreach ($aBoxes as $box) {
        $this->insert($box);
      }
    }

  }
