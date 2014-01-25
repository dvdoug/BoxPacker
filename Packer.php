<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
  namespace DVDoug\BoxPacker;

  use Psr\Log\LoggerAwareInterface;
  use Psr\Log\LoggerAwareTrait;
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
      $this->items = new ItemList;
      $this->boxes = new BoxList;

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
      $this->logger->info("added {$aQty} x {$aItem->getDescription()}");
    }

    /**
     * Add box size
     * @param Box $aBox
     */
    public function addBox(Box $aBox) {
      $this->boxes->insert($aBox);
      $this->logger->info("added box {$aBox->getReference()}");
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

      $this->logger->info("packing started");

      if (!sizeof($this->items)) {
        throw new \RuntimeException('Please specify at least 1 item to be packed');
      }

      if (!sizeof($this->boxes)) {
        throw new \RuntimeException('Please specify at least 1 size of box to pack items into');
      }

      $packedBoxes = $this->doVolumePacking();

      /*
       * If we have multiple boxes, try and optimise/even-out weight distribution
       */
      if ($packedBoxes->count() > 1 && $packedBoxes->getWeightVariance() > 0) {
        $packedBoxes = $this->redistributeWeight($packedBoxes);
      }

      $this->logger->info("packing completed, {$packedBoxes->count()} boxes");

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
     * Given a solution set of packed boxes, repack them to achieve optimum weight distribution
     *
     * @param PackedBoxList $aPackedBoxes
     * @return PackedBoxList
     */
    public function redistributeWeight(PackedBoxList $aPackedBoxes) {
      $targetWeight = $aPackedBoxes->getMeanWeight();
      $this->logger->debug("repacking for weight distribution, weight variance {$aPackedBoxes->getWeightVariance()}, target weight {$targetWeight}");

      /*
       * Keep moving items from most overweight box to most underweight box
       */
      $packedBoxes = clone $aPackedBoxes;
      do {
        $tryRepack = false;

        //Transfer boxes into 3 categories
        $overWeightBoxes = [];
        $underWeightBoxes = [];
        $targetWeightBoxes = [];
        foreach ($packedBoxes as $packedBox) {
          $boxWeight = $packedBox->getWeight();
          if ($boxWeight > $targetWeight) {
            $overWeightBoxes[] = $packedBox;
          }
          else if ($boxWeight < $targetWeight) {
            $underWeightBoxes[] = $packedBox;
          }
          else {
            $targetWeightBoxes[] = $packedBox;
          }
        }
        $this->logger->debug("boxes over weight target: " . count($overWeightBoxes));
        $this->logger->debug("boxes under weight target: " . count($underWeightBoxes));
        $this->logger->debug("boxes exactly on weight target: " . count($targetWeightBoxes));

        foreach ($underWeightBoxes as $u => $underWeightBox) {
          foreach ($overWeightBoxes as $o => $overWeightBox) {

            //Get list of items in box
            $overWeightBoxItems = [];
            foreach (clone $overWeightBox->getItems() as $overWeightBoxItem) {
              $overWeightBoxItems[] = $overWeightBoxItem;
            }

            /*
             * For each item in the heavier box, try and move it to the lighter one
             */
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
              foreach ($newItemsForLighterBox as $item) {
                $newLighterBoxPacker->addItem($item);
              }
              $newLighterBoxPacking = $newLighterBoxPacker->doVolumePacking();

              if ($newLighterBoxPacking->count() == 1) { //new item fits

                $newLighterBox = $newLighterBoxPacking->extract();

                //we may be able to use a smaller box so do a full repack calculation
                $newItemsForOverWeightBox = $overWeightBoxItems;
                unset($newItemsForOverWeightBox[$oi]); //now packed in different box
                $newHeavierBoxPacker = new Packer();
                $newHeavierBoxPacker->setBoxes($this->boxes);
                foreach ($newItemsForOverWeightBox as $item) {
                  $newHeavierBoxPacker->addItem($item);
                }

                $newHeavierBoxPacking = $newHeavierBoxPacker->doVolumePacking();
                $newHeavierBox = $newHeavierBoxPacking->extract();

                $underWeightBoxes[$u] = $underWeightBox = $newLighterBox;
                $overWeightBoxes[$o] = $overWeightBox = $newHeavierBox;
                $tryRepack = true; //we did some work, so see if we can do even better
                break 3;
              }
            }
          }
        }

        //Combine the 3 box classifications back into a single list
        $packedBoxes = new PackedBoxList;
        foreach (array_merge($overWeightBoxes, $underWeightBoxes, $targetWeightBoxes) as $box) {
          $packedBoxes->insert($box);
        }
      } while ($tryRepack);

      return $packedBoxes;
    }


    /**
     * Pack as many items as possible into specific given box
     * @param Box      $aBox
     * @param ItemList $aItems
     * @return ItemList items packed into box
     */
    public function packBox(Box $aBox, ItemList $aItems) {

      $this->logger->debug("evaluating box {$aBox->getReference()}");

      $packedItems = new ItemList;
      $remainingDepth = $aBox->getInnerDepth();
      $remainingWeight = $aBox->getMaxWeight() - $aBox->getEmptyWeight();

      //Define length as longer of 2 dimensions
      $horizontalDimensions = array($aBox->getInnerWidth(), $aBox->getInnerLength());
      sort($horizontalDimensions);
      $remainingWidth = $horizontalDimensions[0];
      $remainingLength = $horizontalDimensions[1];

      $layerWidth = 0;
      $layerLength = 0;
      $layerDepth = 0;

      $packedDepth = 0;

      while(!$aItems->isEmpty() && $aItems->top()->getDepth() <= ($layerDepth ?: $remainingDepth) && $aItems->top()->getWeight() <= $remainingWeight) {

        $this->logger->debug("evaluating item {$aItems->top()->getDescription()}");
        $this->logger->debug("remainingWidth: {$remainingWidth}");
        $this->logger->debug("remainingLength: {$remainingLength}");
        $this->logger->debug("remainingDepth: {$remainingDepth}");
        $this->logger->debug("layerWidth: {$layerWidth}");
        $this->logger->debug("layerLength: {$layerLength}");
        $this->logger->debug("layerDepth: {$layerDepth}");
        $this->logger->debug("packedDepth: {$packedDepth}");

        $itemWidth = $aItems->top()->getWidth();
        $itemLength = $aItems->top()->getLength();

        $fitsSameGap = min($remainingWidth - $itemWidth, $remainingLength - $itemLength);
        $fitsRotatedGap = min($remainingWidth - $itemLength, $remainingLength - $itemWidth);

        if ($fitsSameGap >= 0 && $fitsRotatedGap < 0) {
          $this->logger->debug("fits only without rotation");

          $itemToPack = $aItems->extract();
          $packedItems->insert($itemToPack);

          $remainingWeight -= $itemToPack->getWeight();

          $remainingLength -= $itemLength;
          $layerWidth += $itemWidth;
          $layerLength += $itemLength;
          $layerDepth = max($layerDepth, $itemToPack->getDepth()); //greater than 0, items will always be less deep

        }
        else if ($fitsSameGap < 0 && $fitsRotatedGap >= 0) {
          $this->logger->debug("fits only with rotation");

          $itemToPack = $aItems->extract();
          $packedItems->insert($itemToPack);

          $remainingWeight -= $itemToPack->getWeight();

          $remainingLength -= $itemWidth;
          $layerWidth += $itemLength;
          $layerLength += $itemWidth;
          $layerDepth = max($layerDepth, $itemToPack->getDepth()); //greater than 0, items will always be less deep
        }
        else if ($fitsSameGap >= 0 && $fitsRotatedGap >= 0) {
          $this->logger->debug("fits both ways");

          $itemToPack = $aItems->extract();
          $packedItems->insert($itemToPack);

          $remainingWeight -= $itemToPack->getWeight();

          if ($fitsSameGap <= $fitsRotatedGap) {
            $remainingLength -= $itemLength;
            $layerWidth += $itemWidth;
            $layerLength += $itemLength;
          }
          else {
            $remainingLength -= $itemWidth;
            $layerWidth += $itemLength;
            $layerLength += $itemWidth;
          }
          $layerDepth = max($layerDepth, $itemToPack->getDepth()); //greater than 0, items will always be less deep
        }
        else if ($fitsSameGap < 0 && $fitsRotatedGap < 0) {
          $this->logger->debug("doesn't fit at all");

          if ($layerWidth) {
            $remainingWidth = min(floor($layerWidth * 1.1), $horizontalDimensions[0]);
            $remainingLength = min(floor($layerLength * 1.1), $horizontalDimensions[1]);
            $layerWidth = 0;
            $layerLength = 0;
          }
          else {
            $this->logger->debug("doesn't fit on layer even when empty");
            break;
          }

          $packedDepth += $layerDepth;
          $layerDepth = 0;
          $remainingDepth = $aBox->getInnerDepth() - $packedDepth;

          $this->logger->debug("starting next vertical layer");
        }
      }
      $this->logger->debug("done with this box");
      return $packedItems;
    }

  }
