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

    public function __construct(Box $aBox, ItemList $aItemList) {
      $this->box = $aBox;
      $this->items = $aItemList;
    }

  }
