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
     * Do a bulk insert
     * @param array $aBoxes
     */
    public function insertFromArray(array $aBoxes) {
      foreach ($aBoxes as $box) {
        $this->insert($box);
      }
    }

    /**
     * Classify boxes into under/on/over mean weight
     * @return array
     */
    public function classifyBoxes() {

      $targetWeight = $this->getMeanWeight();

      $boxes = ['overWeight'   => [],
                'underWeight'  => [],
                'targetWeight' => []];
      foreach (clone $this as $box) {
        $boxWeight = $box->getWeight();
        if ($boxWeight > $targetWeight) {
          $boxes['overWeight'][] = $box;
        }
        else if ($boxWeight < $targetWeight) {
          $boxes['underWeight'][] = $box;
        }
        else {
          $boxes['targetWeight'][] = $box;
        }
      }

      return $boxes;
    }

  }
