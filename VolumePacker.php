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
class VolumePacker implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Box to pack items into
     * @var Box
     */
    protected $box;

    /**
     * List of items to be packed
     * @var ItemList
     */
    protected $items;

    /**
     * Remaining width of the box to pack items into
     * @var int
     */
    protected $widthLeft;

    /**
     * Remaining length of the box to pack items into
     * @var int
     */
    protected $lengthLeft;

    /**
     * Remaining depth of the box to pack items into
     * @var int
     */
    protected $depthLeft;

    /**
     * Remaining weight capacity of the box
     * @var int
     */
    protected $remainingWeight;

    /**
     * Constructor
     */
    public function __construct(Box $box, ItemList $items)
    {
        $this->logger = new NullLogger();

        $this->box = $box;
        $this->items = $items;

        $this->depthLeft = $this->box->getInnerDepth();
        $this->remainingWeight = $this->box->getMaxWeight() - $this->box->getEmptyWeight();
        $this->widthLeft = $this->box->getInnerWidth();
        $this->lengthLeft = $this->box->getInnerLength();
    }

    /**
     * Pack as many items as possible into specific given box
     * @return PackedBox packed box
     */
    public function pack()
    {
        $this->logger->debug("[EVALUATING BOX] {$this->box->getReference()}");

        $packedItems = new ItemList;

        $layerWidth = $layerLength = $layerDepth = 0;

        $prevItem = null;

        while (!$this->items->isEmpty()) {

            $itemToPack = $this->items->extract();

            //skip items that are simply too heavy
            if ($itemToPack->getWeight() > $this->remainingWeight) {
                continue;
            }

            $this->logger->debug("evaluating item {$itemToPack->getDescription()}");
            $this->logger->debug("remaining width: {$this->widthLeft}, length: {$this->lengthLeft}, depth: {$this->depthLeft}");
            $this->logger->debug("layerWidth: {$layerWidth}, layerLength: {$layerLength}, layerDepth: {$layerDepth}");

            $nextItem = !$this->items->isEmpty() ? $this->items->top() : null;
            $orientatedItem = $this->findBestOrientation($itemToPack, $prevItem, $nextItem, $this->widthLeft, $this->lengthLeft, $this->depthLeft);

            if ($orientatedItem) {

                $packedItems->insert($orientatedItem->getItem());
                $this->remainingWeight -= $itemToPack->getWeight();

                $this->lengthLeft -= $orientatedItem->getLength();
                $layerLength += $orientatedItem->getLength();
                $layerWidth = max($orientatedItem->getWidth(), $layerWidth);

                $layerDepth = max($layerDepth, $orientatedItem->getDepth()); //greater than 0, items will always be less deep

                //allow items to be stacked in place within the same footprint up to current layerdepth
                $stackableDepth = $layerDepth - $orientatedItem->getDepth();
                $this->tryAndStackItemsIntoSpace($packedItems, $orientatedItem->getWidth(), $orientatedItem->getLength(), $stackableDepth);

                $prevItem = $orientatedItem;
            } else {

                $prevItem = null;

                if ($this->widthLeft >= min($itemToPack->getWidth(), $itemToPack->getLength()) && $this->isLayerStarted($layerWidth, $layerLength, $layerDepth)) {
                    $this->logger->debug("No more fit in lengthwise, resetting for new row");
                    $this->lengthLeft += $layerLength;
                    $this->widthLeft -= $layerWidth;
                    $layerWidth = $layerLength = 0;
                    $this->items->insert($itemToPack);
                    continue;
                } elseif ($this->lengthLeft < min($itemToPack->getWidth(), $itemToPack->getLength()) || $layerDepth == 0) {
                    $this->logger->debug("doesn't fit on layer even when empty");
                    continue;
                }

                $this->widthLeft = $layerWidth ? min(floor($layerWidth * 1.1), $this->box->getInnerWidth()) : $this->box->getInnerWidth();
                $this->lengthLeft = $layerLength ? min(floor($layerLength * 1.1), $this->box->getInnerLength()) : $this->box->getInnerLength();
                $this->depthLeft -= $layerDepth;

                $layerWidth = $layerLength = $layerDepth = 0;
                $this->logger->debug("doesn't fit, so starting next vertical layer");
                $this->items->insert($itemToPack);
            }
        }
        $this->logger->debug("done with this box");
        return new PackedBox($this->box, $packedItems, $this->widthLeft, $this->lengthLeft, $this->depthLeft, $this->remainingWeight);
    }

    /**
     * Get the best orientation for an item
     * @param Item $item
     * @param OrientatedItem|null $prevItem
     * @param Item|null $nextItem
     * @param int $widthLeft
     * @param int $lengthLeft
     * @param int $depthLeft
     * @return OrientatedItem|false
     */
    protected function findBestOrientation(Item $item, OrientatedItem $prevItem = null, Item $nextItem = null, $widthLeft, $lengthLeft, $depthLeft) {

        $orientations = $this->findPossibleOrientations($item, $prevItem, $widthLeft, $lengthLeft, $depthLeft);

        if (empty($orientations)) {
            return false;
        }

        // special casing based on next item
        if (isset($orientations[0]) && $nextItem == $item && $lengthLeft >= 2 * $item->getLength()) {
            $this->logger->debug("not rotating based on next item");
            return $orientations[0]; // XXX this is tied to the ordering from ->findPossibleOrientations()
        }

        $orientationFits = [];

        /** @var OrientatedItem $orientation */
        foreach ($orientations as $o => $orientation) {
            $orientationFit = min($widthLeft   - $orientation->getWidth(), $lengthLeft  - $orientation->getLength());
            $orientationFits[$o] = $orientationFit;
        }

        if (!empty($orientationFits)) {
            asort($orientationFits);
            reset($orientationFits);
            $bestFit = key($orientationFits);
            $this->logger->debug("Using orientation #{$bestFit}");
            return $orientations[$bestFit];
        } else {
            return false;
        }
    }

    /**
     * Find all possible orientations for an item
     * @param Item $item
     * @param OrientatedItem|null $prevItem
     * @param int $widthLeft
     * @param int $lengthLeft
     * @param int $depthLeft
     * @return OrientatedItem[]
     */
    protected function findPossibleOrientations(Item $item, OrientatedItem $prevItem = null, $widthLeft, $lengthLeft, $depthLeft) {

        $orientations = [];

        //Special case items that are the same as what we just packed - keep orientation
        if ($prevItem && $prevItem->getItem() == $item) {
            $orientations[] = new OrientatedItem($item, $prevItem->getWidth(), $prevItem->getLength(), $prevItem->getDepth());
        } else {

            //simple 2D rotation
            $orientations[] = new OrientatedItem($item, $item->getWidth(), $item->getLength(), $item->getDepth());
            $orientations[] = new OrientatedItem($item, $item->getLength(), $item->getWidth(), $item->getDepth());

            //add 3D rotation if we're allowed
            if (!$item->getKeepFlat()) {
                $orientations[] = new OrientatedItem($item, $item->getWidth(), $item->getDepth(), $item->getLength());
                $orientations[] = new OrientatedItem($item, $item->getLength(), $item->getDepth(), $item->getWidth());
                $orientations[] = new OrientatedItem($item, $item->getDepth(), $item->getWidth(), $item->getLength());
                $orientations[] = new OrientatedItem($item, $item->getDepth(), $item->getLength(), $item->getWidth());
            }
        }

        //remove any that simply don't fit
        return array_filter($orientations, function (OrientatedItem $i) use ($widthLeft, $lengthLeft, $depthLeft) {
            return $i->getWidth() <= $widthLeft && $i->getLength() <= $lengthLeft && $i->getDepth() <= $depthLeft;
        });

    }

    /**
     * Figure out if we can stack the next item vertically on top of this rather than side by side
     * Used when we've packed a tall item, and have just put a shorter one next to it
     * @param ItemList $packedItems
     * @param int $maxWidth
     * @param int $maxLength
     * @param int $maxDepth
     * @return bool
     */
    protected function tryAndStackItemsIntoSpace(ItemList $packedItems, $maxWidth, $maxLength, $maxDepth)
    {
        while (!$this->items->isEmpty() && $this->remainingWeight >= $this->items->top()->getWeight()) {
            $stackedItem = $this->findBestOrientation($this->items->top(), null, null, $maxWidth, $maxLength, $maxDepth);
            if ($stackedItem) {
                $this->remainingWeight -= $this->items->top()->getWeight();
                $maxDepth -= $stackedItem->getDepth();
                $packedItems->insert($this->items->extract());
            } else {
                break;
            }
        }
    }

    /**
     * @param int $layerWidth
     * @param int $layerLength
     * @param int $layerDepth
     * @return bool
     */
    protected function isLayerStarted($layerWidth, $layerLength, $layerDepth) {
        return $layerWidth > 0 && $layerLength > 0 && $layerDepth > 0;
    }
}
