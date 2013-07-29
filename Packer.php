<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
  namespace DVDoug\BoxPacker;

  /**
   * Actual packer
   * @author Doug Wright
   * @package BoxPacker
   */
  class Packer  {

    /**
     * List of items to be packed
     * @var ItemList
     */
    protected $items;

    /**
     * List of box sizes available to pack items into
     * @var Box[]
     */
    protected $boxes;

    public function __construct() {
      $this->items = new ItemList;
      $this->boxes = new BoxList;
    }

    /**
     * Add item to be packed
     * @param Item $aItem
     * @param int  $aQty
     */
    public function addItem(Item $aItem, $aQty = 1) {
      for ($i = 0; $i < $aQty; $i++) {
        $this->items->insert($aItem);
      }
    }

    /**
     * Add box size
     * @param Box $aBox
     */
    public function addBox(Box $aBox) {
      $this->boxes->insert($aBox);
    }

    /**
     * Pack items into boxes
     * @return PackedBox[]
     */
    public function pack() {
      if (!sizeof($this->items)) {
        throw new \RuntimeException('Please specify at least 1 item to be packed');
      }

      if (!sizeof($this->boxes)) {
        throw new \RuntimeException('Please specify at least 1 size of box to pack items into');
      }

      $packedBoxes = new PackedBoxList;
      $unpackedItems = $this->items;

      /*
       * Keep going until everything packed
       */
      while ($unpackedItems->count()) {
        $boxesToEvaluate = clone $this->boxes;
        $packedBoxesIteration = new PackedBoxList;
        /*
         * Loop through boxes starting with smallest, see what happens
         */
        while (!$boxesToEvaluate->isEmpty()) {
          $box = $boxesToEvaluate->extract();
          $packedItems = $this->packBox($box, clone $unpackedItems);
          if ($packedItems->count()) {
            $packedBoxesIteration->insert(new PackedBox($box, $packedItems));

            //Have we found a single box that contains everything?
            if ($packedItems->count() == $unpackedItems->count()) {
              break;
            }
          }
        }

        /*
         * Check iteration was productive
         */
        if ($packedBoxesIteration->isEmpty()) {
          throw new \RuntimeException('Item ' . $unpackedItems->top()->getDescription() . ' is too large to fit into any box');
        }

        /*
         * Find best box of iteration, and remove packed items from unpacked list
         */
        $bestBox = $packedBoxesIteration->top();
        for ($i = 0; $i < $bestBox->getItems()->count(); $i++) {
          $unpackedItems->extract();
        }
        $packedBoxes->insert($bestBox);

      }
      return $packedBoxes;
    }


    /**
     * Pack as many items as possible into specific given box
     * XXX for now, simply stacks items on top of each other, no side-by-side evaluation performed yet
     * @param Box      $aBox
     * @param ItemList $aItems
     * @return ItemList items packed into box
     */
    public function packBox(Box $aBox, ItemList $aItems) {

      $packedItems = new ItemList;
      $remainingDepth = $aBox->getInnerDepth();
      $remainingWeight = $aBox->getMaxWeight() - $aBox->getEmptyWeight();
      while(!$aItems->isEmpty() && $aItems->top()->getDepth() <= $remainingDepth && $aItems->top()->getWeight() <= $remainingWeight) {
        $itemD1 = $aItems->top()->getWidth();
        $itemD2 = $aItems->top()->getLength();
        $boxD1 = $aBox->getInnerWidth();
        $boxD2 = $aBox->getInnerLength();

        if (($itemD1 <= $boxD1 && $itemD2 <= $boxD2) || ($itemD2 <= $boxD1 && $itemD1 <= $boxD2)) { //check 2D rotation
          $itemToPack = $aItems->extract();
          $remainingDepth -= $itemToPack->getDepth();
          $remainingWeight -= $itemToPack->getWeight();
          $packedItems->insert($itemToPack);
        }
        else {
          break;
        }
      }
      return $packedItems;
    }

  }
