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
     * @var float
     */
    protected $weight;

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

    public function __construct(Box $aBox, ItemList $aItemList) {
      $this->box = $aBox;
      $this->items = $aItemList;
    }

  }
