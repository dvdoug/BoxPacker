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
class Packer implements LoggerAwareInterface
{
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
    public function __construct()
    {
        $this->items = new ItemList();
        $this->boxes = new BoxList();

        $this->logger = new NullLogger();
    }

    /**
     * Add item to be packed
     * @param Item $item
     * @param int  $qty
     */
    public function addItem(Item $item, $qty = 1)
    {
        for ($i = 0; $i < $qty; $i++) {
            $this->items->insert($item);
        }
        $this->logger->log(LogLevel::INFO, "added {$qty} x {$item->getDescription()}");
    }

    /**
     * Set a list of items all at once
     * @param \Traversable|array $items
     */
    public function setItems($items)
    {
        if ($items instanceof ItemList) {
            $this->items = clone $items;
        } else {
            $this->items = new ItemList();
            foreach ($items as $item) {
                $this->items->insert($item);
            }
        }
    }

    /**
     * Add box size
     * @param Box $box
     */
    public function addBox(Box $box)
    {
        $this->boxes->insert($box);
        $this->logger->log(LogLevel::INFO, "added box {$box->getReference()}");
    }

    /**
     * Add a pre-prepared set of boxes all at once
     * @param BoxList $boxList
     */
    public function setBoxes(BoxList $boxList)
    {
        $this->boxes = clone $boxList;
    }

    /**
     * Pack items into boxes
     *
     * @throws \RuntimeException
     * @return PackedBoxList
     */
    public function pack()
    {
        $packedBoxes = $this->doVolumePacking();

        //If we have multiple boxes, try and optimise/even-out weight distribution
        if ($packedBoxes->count() > 1) {
            $redistributor = new WeightRedistributor($this->boxes);
            $packedBoxes = $redistributor->redistributeWeight($packedBoxes);
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
    public function doVolumePacking()
    {

        $packedBoxes = new PackedBoxList;

        //Keep going until everything packed
        while ($this->items->count()) {
            $boxesToEvaluate = clone $this->boxes;
            $packedBoxesIteration = new PackedBoxList;

            //Loop through boxes starting with smallest, see what happens
            while (!$boxesToEvaluate->isEmpty()) {
                $box = $boxesToEvaluate->extract();
                $packedBox = $this->packIntoBox($box, clone $this->items);
                if ($packedBox->getItems()->count()) {
                    $packedBoxesIteration->insert($packedBox);

                    //Have we found a single box that contains everything?
                    if ($packedBox->getItems()->count() === $this->items->count()) {
                        break;
                    }
                }
            }

            //Check iteration was productive
            if ($packedBoxesIteration->isEmpty()) {
                throw new \RuntimeException('Item ' . $this->items->top()->getDescription() . ' is too large to fit into any box');
            }

            //Find best box of iteration, and remove packed items from unpacked list
            $bestBox = $packedBoxesIteration->top();
            $unPackedItems = $this->items->asArray();
            foreach (clone $bestBox->getItems() as $packedItem) {
                foreach ($unPackedItems as $unpackedKey => $unpackedItem) {
                    if ($packedItem === $unpackedItem) {
                        unset($unPackedItems[$unpackedKey]);
                        break;
                    }
                }
            }
            $unpackedItemList = new ItemList();
            foreach ($unPackedItems as $unpackedItem) {
                $unpackedItemList->insert($unpackedItem);
            }
            $this->items = $unpackedItemList;
            $packedBoxes->insert($bestBox);

        }

        return $packedBoxes;
    }

    /**
     * Pack as many items as possible into specific given box
     * @param Box      $box
     * @param ItemList $items
     * @return PackedBox packed box
     */
    public function packIntoBox(Box $box, ItemList $items)
    {
        $this->logger->log(LogLevel::DEBUG, "[EVALUATING BOX] {$box->getReference()}");

        $packedItems = new ItemList;
        $remainingDepth = $box->getInnerDepth();
        $remainingWeight = $box->getMaxWeight() - $box->getEmptyWeight();
        $remainingWidth = $box->getInnerWidth();
        $remainingLength = $box->getInnerLength();

        $layerWidth = $layerLength = $layerDepth = 0;
        while (!$items->isEmpty()) {

            $itemToPack = $items->top();

            //skip items that are simply too large
            if ($this->isItemTooLargeForBox($itemToPack, $remainingDepth, $remainingWeight)) {
                $items->extract();
                continue;
            }

            $this->logger->log(LogLevel::DEBUG, "evaluating item {$itemToPack->getDescription()}");
            $this->logger->log(LogLevel::DEBUG, "remaining width: {$remainingWidth}, length: {$remainingLength}, depth: {$remainingDepth}");
            $this->logger->log(LogLevel::DEBUG, "layerWidth: {$layerWidth}, layerLength: {$layerLength}, layerDepth: {$layerDepth}");

            $itemWidth = $itemToPack->getWidth();
            $itemLength = $itemToPack->getLength();

            if ($this->fitsGap($itemToPack, $remainingWidth, $remainingLength)) {

                $packedItems->insert($items->extract());
                $remainingWeight -= $itemToPack->getWeight();

                $nextItem = !$items->isEmpty() ? $items->top() : null;
                if ($this->fitsBetterRotated($itemToPack, $nextItem, $remainingWidth, $remainingLength)) {
                    $this->logger->log(LogLevel::DEBUG, "fits (better) unrotated");
                    $remainingLength -= $itemLength;
                    $layerLength += $itemLength;
                    $layerWidth = max($itemWidth, $layerWidth);
                } else {
                    $this->logger->log(LogLevel::DEBUG, "fits (better) rotated");
                    $remainingLength -= $itemWidth;
                    $layerLength += $itemWidth;
                    $layerWidth = max($itemLength, $layerWidth);
                }
                $layerDepth = max($layerDepth, $itemToPack->getDepth()); //greater than 0, items will always be less deep

                //allow items to be stacked in place within the same footprint up to current layerdepth
                $maxStackDepth = $layerDepth - $itemToPack->getDepth();
                while (!$items->isEmpty() && $this->canStackItemInLayer($itemToPack, $items->top(), $maxStackDepth, $remainingWeight)) {
                    $remainingWeight -= $items->top()->getWeight();
                    $maxStackDepth -= $items->top()->getDepth();
                    $packedItems->insert($items->extract());
                }
            } else {
                if ($remainingWidth >= min($itemWidth, $itemLength) && $this->isLayerStarted($layerWidth, $layerLength, $layerDepth)) {
                    $this->logger->log(LogLevel::DEBUG, "No more fit in lengthwise, resetting for new row");
                    $remainingLength += $layerLength;
                    $remainingWidth -= $layerWidth;
                    $layerWidth = $layerLength = 0;
                    continue;
                } elseif ($remainingLength < min($itemWidth, $itemLength) || $layerDepth == 0) {
                    $this->logger->log(LogLevel::DEBUG, "doesn't fit on layer even when empty");
                    $items->extract();
                    continue;
                }

                $remainingWidth = $layerWidth ? min(floor($layerWidth * 1.1), $box->getInnerWidth()) : $box->getInnerWidth();
                $remainingLength = $layerLength ? min(floor($layerLength * 1.1), $box->getInnerLength()) : $box->getInnerLength();
                $remainingDepth -= $layerDepth;

                $layerWidth = $layerLength = $layerDepth = 0;
                $this->logger->log(LogLevel::DEBUG, "doesn't fit, so starting next vertical layer");
            }
        }
        $this->logger->log(LogLevel::DEBUG, "done with this box");
        return new PackedBox($box, $packedItems, $remainingWidth, $remainingLength, $remainingDepth, $remainingWeight);
    }

    /**
     * @param Item $item
     * @param int $remainingDepth
     * @param int $remainingWeight
     * @return bool
     */
    protected function isItemTooLargeForBox(Item $item, $remainingDepth, $remainingWeight) {
        return $item->getDepth() > $remainingDepth || $item->getWeight() > $remainingWeight;
    }

    /**
     * Figure out space left for next item if we pack this one in it's regular orientation
     * @param Item $item
     * @param int $remainingWidth
     * @param int $remainingLength
     * @return int
     */
    protected function fitsSameGap(Item $item, $remainingWidth, $remainingLength) {
        return min($remainingWidth - $item->getWidth(), $remainingLength - $item->getLength());
    }

    /**
     * Figure out space left for next item if we pack this one rotated by 90deg
     * @param Item $item
     * @param int $remainingWidth
     * @param int $remainingLength
     * @return int
     */
    protected function fitsRotatedGap(Item $item, $remainingWidth, $remainingLength) {
        return min($remainingWidth - $item->getLength(), $remainingLength - $item->getWidth());
    }

    /**
     * @param Item $item
     * @param Item|null $nextItem
     * @param $remainingWidth
     * @param $remainingLength
     * @return bool
     */
    protected function fitsBetterRotated(Item $item, Item $nextItem = null, $remainingWidth, $remainingLength) {

        $fitsSameGap = $this->fitsSameGap($item, $remainingWidth, $remainingLength);
        $fitsRotatedGap = $this->fitsRotatedGap($item, $remainingWidth, $remainingLength);

        return !!($fitsRotatedGap < 0 ||
        ($fitsSameGap >= 0 && $fitsSameGap <= $fitsRotatedGap) ||
        ($item->getWidth() <= $remainingWidth && $nextItem == $item && $remainingLength >= 2 * $item->getLength()));
    }

    /**
     * Does item fit in specified gap
     * @param Item $item
     * @param $remainingWidth
     * @param $remainingLength
     * @return bool
     */
    protected function fitsGap(Item $item, $remainingWidth, $remainingLength) {
        return $this->fitsSameGap($item, $remainingWidth, $remainingLength) >= 0 ||
               $this->fitsRotatedGap($item, $remainingWidth, $remainingLength) >= 0;
    }

    /**
     * Figure out if we can stack the next item vertically on top of this rather than side by side
     * Used when we've packed a tall item, and have just put a shorter one next to it
     * @param Item $item
     * @param Item $nextItem
     * @param $maxStackDepth
     * @param $remainingWeight
     * @return bool
     */
    protected function canStackItemInLayer(Item $item, Item $nextItem, $maxStackDepth, $remainingWeight)
    {
        return $nextItem->getDepth() <= $maxStackDepth &&
               $nextItem->getWeight() <= $remainingWeight &&
               $nextItem->getWidth() <= $item->getWidth() &&
               $nextItem->getLength() <= $item->getLength();
    }

    /**
     * @param $layerWidth
     * @param $layerLength
     * @param $layerDepth
     * @return bool
     */
    protected function isLayerStarted($layerWidth, $layerLength, $layerDepth) {
        return $layerWidth > 0 && $layerLength > 0 && $layerDepth > 0;
    }
}
