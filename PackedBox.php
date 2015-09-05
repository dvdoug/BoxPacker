<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
  namespace DVDoug\BoxPacker;

  /**
   * A "box" with items
   * @author Doug Wright
   * @package BoxPacker
   */
  class PackedBox {

    /**
     * Box used
     * @var Box
     */
    protected $box;

    /**
     * Items in the box
     * @var ItemList
     */
    protected $items;

    /**
     * Total weight of box
     * @var int
     */
    protected $weight;

    /**
     * Remaining width inside box for another item
     * @var int
     */
    protected $remainingWidth;

    /**
     * Remaining length inside box for another item
     * @var int
     */
    protected $remainingLength;

    /**
     * Remaining depth inside box for another item
     * @var int
     */
    protected $remainingDepth;

    /**
     * Remaining weight inside box for another item
     * @var int
     */
    protected $remainingWeight;

    /**
     * Get box used
     * @return Box
     */
    public function getBox() {
      return $this->box;
    }

    /**
     * Get items packed
     * @return ItemList
     */
    public function getItems() {
      return $this->items;
    }

    /**
     * Get packed weight
     * @return int weight in grams
     */
    public function getWeight() {

      if (!is_null($this->weight)) {
        return $this->weight;
      }

      $this->weight = $this->box->getEmptyWeight();
      $items = clone $this->items;
      foreach ($items as $item) {
        $this->weight += $item->getWeight();
      }
      return $this->weight;
    }

    /**
     * Get remaining width inside box for another item
     * @return int
     */
    public function getRemainingWidth() {
      return $this->remainingWidth;
    }

    /**
     * Get remaining length inside box for another item
     * @return int
     */
    public function getRemainingLength() {
      return $this->remainingLength;
    }

    /**
     * Get remaining depth inside box for another item
     * @return int
     */
    public function getRemainingDepth() {
      return $this->remainingDepth;
    }

    /**
     * Get remaining weight inside box for another item
     * @return int
     */
    public function getRemainingWeight() {
      return $this->remainingWeight;
    }

    /**
     * Get volume utilisation of the packed box
     * @return float
     */
    public function getVolumeUtilisation() {
      $itemVolume = 0;

      /** @var Item $item */
      foreach (clone $this->items as $item) {
        $itemVolume += $item->getVolume();
      }

      return round($itemVolume / $this->box->getInnerVolume() * 100, 1);
    }



    /**
     * Constructor
     * @param Box      $aBox
     * @param ItemList $aItemList
     * @param int      $aRemainingWidth
     * @param int      $aRemainingLength
     * @param int      $aRemainingDepth
     * @param int      $aRemainingWeight
     */
    public function __construct(Box $aBox, ItemList $aItemList, $aRemainingWidth, $aRemainingLength, $aRemainingDepth, $aRemainingWeight) {
      $this->box = $aBox;
      $this->items = $aItemList;
      $this->remainingWidth = $aRemainingWidth;
      $this->remainingLength = $aRemainingLength;
      $this->remainingDepth = $aRemainingDepth;
      $this->remainingWeight = $aRemainingWeight;
    }

  }
