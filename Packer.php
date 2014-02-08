<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
  namespace DVDoug\BoxPacker;

  use Psr\Log\LoggerAwareInterface;
  use Psr\Log\LoggerAwareTrait;
  use Psr\Log\LogLevel;
  use Psr\Log\NullLogger;

  /**
   * Actual packer
   * @author Doug Wright
   * @package BoxPacker
   */
  class Packer implements LoggerAwareInterface {
    use LoggerAwareTrait;

    /**
     * List of items to be packed
     * @var ItemList
     */
    protected $items;

    /**
     * List of box sizes available to pack items into
     * @var BoxList
     */
    protected $boxes;

    /**
     * Constructor
     */
    public function __construct() {
      $this->items = new ItemList();
      $this->boxes = new BoxList();

      $this->logger = new NullLogger();
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
      $this->logger->log(LogLevel::INFO, "added {$aQty} x {$aItem->getDescription()}");
    }

    /**
     * Set a list of items all at once
     * @param \Traversable $aItems
     */
    public function setItems($aItems) {
      if ($aItems instanceof ItemList) {
        $this->items = clone $aItems;
      }
      else if (is_array($aItems)) {
        $this->items = new ItemList();
        foreach ($aItems as $item) {
          $this->items->insert($item);
        }
      }
      else {
        throw new \RuntimeException('Not a valid list of items');
      }
    }

    /**
     * Add box size
     * @param Box $aBox
     */
    public function addBox(Box $aBox) {
      $this->boxes->insert($aBox);
      $this->logger->log(LogLevel::INFO, "added box {$aBox->getReference()}");
    }

    /**
     * Add a pre-prepared set of boxes all at once
     * @param BoxList $aBoxList
     */
    public function setBoxes(BoxList $aBoxList) {
      $this->boxes = clone $aBoxList;
    }

    /**
     * Pack items into boxes
     *
     * @throws \RuntimeException
     * @return PackedBoxList
     */
    public function pack() {
      $packedBoxes = $this->doVolumePacking();

      //If we have multiple boxes, try and optimise/even-out weight distribution
      if ($packedBoxes->count() > 1) {
        $packedBoxes = $this->redistributeWeight($packedBoxes);
      }

      $this->logger->log(LogLevel::INFO, "packing completed, {$packedBoxes->count()} boxes");
      return $packedBoxes;
    }

    /**
     * Pack items into boxes using the principle of largest volume item first
     *
     * @throws \RuntimeException
     * @return PackedBoxList
     */
    public function doVolumePacking() {

      $packedBoxes = new PackedBoxList;
      $unpackedItems = $this->items;

      //Keep going until everything packed
      while ($unpackedItems->count()) {
        $boxesToEvaluate = clone $this->boxes;
        $packedBoxesIteration = new PackedBoxList;

        //Loop through boxes starting with smallest, see what happens
        while (!$boxesToEvaluate->isEmpty()) {
          $box = $boxesToEvaluate->extract();
          $packedItems = $this->packBox($box, clone $unpackedItems);
          if ($packedItems->count()) {
            $packedBoxesIteration->insert(new PackedBox($box, $packedItems));

            //Have we found a single box that contains everything?
            if ($packedItems->count() === $unpackedItems->count()) {
              break;
            }
          }
        }

        //Check iteration was productive
        if ($packedBoxesIteration->isEmpty()) {
          throw new \RuntimeException('Item ' . $unpackedItems->top()->getDescription() . ' is too large to fit into any box');
        }

        //Find best box of iteration, and remove packed items from unpacked list
        $bestBox = $packedBoxesIteration->top();
        for ($i = 0; $i < $bestBox->getItems()->count(); $i++) {
          $unpackedItems->extract();
        }
        $packedBoxes->insert($bestBox);

      }

      return $packedBoxes;
    }

    /**
     * Given a solution set of packed boxes, repack them to achieve optimum weight distribution
     *
     * @param PackedBoxList $aPackedBoxes
     * @return PackedBoxList
     */
    public function redistributeWeight(PackedBoxList $aPackedBoxes) {
      $targetWeight = $aPackedBoxes->getMeanWeight();
      $this->logger->log(LogLevel::DEBUG,  "repacking for weight distribution, weight variance {$aPackedBoxes->getWeightVariance()}, target weight {$targetWeight}");

      $packedBoxes = new PackedBoxList;

      //Find out which boxes are over/under the target weight
      $overWeightBoxes = [];
      $underWeightBoxes = [];
      foreach ($aPackedBoxes as $packedBox) {
        $boxWeight = $packedBox->getWeight();
        if ($boxWeight > $targetWeight) {
          $overWeightBoxes[] = $packedBox;
        }
        else if ($boxWeight < $targetWeight) {
          $underWeightBoxes[] = $packedBox;
        }
        else {
          $packedBoxes->insert($packedBox);
        }
      }

      //Keep moving items from most overweight box to most underweight box
      do {
        $tryRepack = false;

        $this->logger->log(LogLevel::DEBUG,  'boxes under/over target: ' . count($underWeightBoxes) . '/' . count($overWeightBoxes));

        foreach ($underWeightBoxes as $u => $underWeightBox) {
          foreach ($overWeightBoxes as $o => $overWeightBox) {

            //Get list of items in box
            $overWeightBoxItems = $overWeightBox->getItems()->asArray();

            //For each item in the heavier box, try and move it to the lighter one
            foreach ($overWeightBoxItems as $oi => $overWeightBoxItem) {

              //skip if moving this item would hinder rather than help weight distribution
              if ($underWeightBox->getWeight() + $overWeightBoxItem->getWeight() > $targetWeight) {
                continue;
              }

              $newItemsForLighterBox = clone $underWeightBox->getItems();
              $newItemsForLighterBox->insert($overWeightBoxItem);

              //we may need a bigger box, so do a full repack calculation rather than box-specific
              $newLighterBoxPacker = new Packer();
              $newLighterBoxPacker->setBoxes($this->boxes);
              $newLighterBoxPacker->setItems($newItemsForLighterBox);
              $newLighterBox = $newLighterBoxPacker->doVolumePacking()->extract();

              if ($newLighterBox->getItems()->count() === $newItemsForLighterBox->count()) { //new item fits

                //we may be able to use a smaller box so do a full repack calculation
                unset($overWeightBoxItems[$oi]); //now packed in different box
                $newHeavierBoxPacker = new Packer();
                $newHeavierBoxPacker->setBoxes($this->boxes);
                $newHeavierBoxPacker->setItems($overWeightBoxItems);

                $overWeightBoxes[$o] = $newHeavierBoxPacker->doVolumePacking()->extract();
                $underWeightBoxes[$u] = $newLighterBox;

                $tryRepack = true; //we did some work, so see if we can do even better
                usort($overWeightBoxes, [$packedBoxes, 'reverseCompare']);
                usort($underWeightBoxes, [$packedBoxes, 'reverseCompare']);
                break 3;
              }
            }
          }
        }

      } while ($tryRepack);

      //Combine back into a single list
      $packedBoxes->insertFromArray($overWeightBoxes);
      $packedBoxes->insertFromArray($underWeightBoxes);

      return $packedBoxes;
    }


    /**
     * Pack as many items as possible into specific given box
     * @param Box      $aBox
     * @param ItemList $aItems
     * @return ItemList items packed into box
     */
    public function packBox(Box $aBox, ItemList $aItems) {
      $this->logger->log(LogLevel::DEBUG,  "evaluating box {$aBox->getReference()}");

      $packedItems = new ItemList;
      $remainingDepth = $aBox->getInnerDepth();
      $remainingWeight = $aBox->getMaxWeight() - $aBox->getEmptyWeight();
      $remainingWidth = $aBox->getInnerWidth();
      $remainingLength = $aBox->getInnerLength();

      $layerWidth = $layerLength = $layerDepth = 0;
      while(!$aItems->isEmpty()) {

        $itemToPack = $aItems->top();

        if ($itemToPack->getDepth() > ($layerDepth ?: $remainingDepth) || $itemToPack->getWeight() > $remainingWeight) {
          break;
        }

        $this->logger->log(LogLevel::DEBUG,  "evaluating item {$itemToPack->getDescription()}");
        $this->logger->log(LogLevel::DEBUG,  "remaining width :{$remainingWidth}, length: {$remainingLength}, depth: {$remainingDepth}");
        $this->logger->log(LogLevel::DEBUG,  "layerWidth: {$layerWidth}, layerLength: {$layerLength}, layerDepth: {$layerDepth}");

        $itemWidth = $itemToPack->getWidth();
        $itemLength = $itemToPack->getLength();

        $fitsSameGap = min($remainingWidth - $itemWidth, $remainingLength - $itemLength);
        $fitsRotatedGap = min($remainingWidth - $itemLength, $remainingLength - $itemWidth);

        if ($fitsSameGap >= 0 || $fitsRotatedGap >= 0) {

          $packedItems->insert($aItems->extract());
          $remainingWeight -= $itemToPack->getWeight();

          if ($fitsSameGap <= $fitsRotatedGap || $fitsRotatedGap < 0 ) {
            $this->logger->log(LogLevel::DEBUG,  "fits (better) unrotated");
            $remainingLength -= $itemLength;
            $layerWidth += $itemWidth;
            $layerLength += $itemLength;
          }
          else {
            $this->logger->log(LogLevel::DEBUG,  "fits (better) rotated");
            $remainingLength -= $itemWidth;
            $layerWidth += $itemLength;
            $layerLength += $itemWidth;
          }
          $layerDepth = max($layerDepth, $itemToPack->getDepth()); //greater than 0, items will always be less deep
        }
        else {
          if (!$layerWidth) {
            $this->logger->log(LogLevel::DEBUG,  "doesn't fit on layer even when empty");
            break;
          }

          $remainingWidth = min(floor($layerWidth * 1.1), $aBox->getInnerWidth());
          $remainingLength = min(floor($layerLength * 1.1), $aBox->getInnerLength());
          $remainingDepth -= $layerDepth;

          $layerWidth = $layerLength = $layerDepth = 0;
          $this->logger->log(LogLevel::DEBUG,  "doesn't fit, so starting next vertical layer");
        }
      }
      $this->logger->log(LogLevel::DEBUG,  "done with this box");
      return $packedItems;
    }
  }
