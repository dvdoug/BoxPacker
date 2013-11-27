<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
  namespace DVDoug\BoxPacker;

  use Psr\Log\LoggerAwareInterface;
  use Psr\Log\LoggerAwareTrait;
  use Psr\Log\LoggerInterface;
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
     * Pack items into boxes
     *
     * @throws \RuntimeException
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
        $notTooMuchShallower = ($layerDepth ? $aItems->top()->getDepth() > ($layerDepth * 0.9) : true);

        if ($notTooMuchShallower && $fitsSameGap >= 0 && $fitsRotatedGap < 0) {
          $this->logger->debug("fits only without rotation");

          $itemToPack = $aItems->extract();
          $packedItems->insert($itemToPack);

          $remainingWeight -= $itemToPack->getWeight();

          $remainingLength -= $itemLength;
          $layerWidth += $itemWidth;
          $layerLength += $itemLength;
          $layerDepth = max($layerDepth, $itemToPack->getDepth()); //greater than 0, items will always be less deep

        }
        else if ($notTooMuchShallower && $fitsSameGap < 0 && $fitsRotatedGap >= 0) {
          $this->logger->debug("fits only with rotation");

          $itemToPack = $aItems->extract();
          $packedItems->insert($itemToPack);

          $remainingWeight -= $itemToPack->getWeight();

          $remainingLength -= $itemWidth;
          $layerWidth += $itemLength;
          $layerLength += $itemWidth;
          $layerDepth = max($layerDepth, $itemToPack->getDepth()); //greater than 0, items will always be less deep
        }
        else if ($notTooMuchShallower && $fitsSameGap >= 0 && $fitsRotatedGap >= 0) {
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
        else if (!$notTooMuchShallower || ($fitsSameGap < 0 && $fitsRotatedGap < 0)) {
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
          $this->logger->debug("remainingWidth: {$remainingWidth}");
          $this->logger->debug("remainingLength: {$remainingLength}");
          $this->logger->debug("remainingDepth: {$remainingDepth}");
          $this->logger->debug("layerWidth: {$layerWidth}");
          $this->logger->debug("layerLength: {$layerLength}");
          $this->logger->debug("layerDepth: {$layerDepth}");
          $this->logger->debug("packedDepth: {$packedDepth}");

        }
      }
      $this->logger->debug("done with this box");
      return $packedItems;
    }

  }
