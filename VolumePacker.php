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
     * Constructor
     */
    public function __construct(Box $box, ItemList $items)
    {
        $this->box = $box;
        $this->items = $items;
        $this->logger = new NullLogger();
    }

    /**
     * Pack as many items as possible into specific given box
     * @return PackedBox packed box
     */
    public function pack()
    {
        $this->logger->debug("[EVALUATING BOX] {$this->box->getReference()}");

        $packedItems = new ItemList;
        $depthLeft = $this->box->getInnerDepth();
        $remainingWeight = $this->box->getMaxWeight() - $this->box->getEmptyWeight();
        $widthLeft = $this->box->getInnerWidth();
        $lengthLeft = $this->box->getInnerLength();

        $layerWidth = $layerLength = $layerDepth = 0;
        while (!$this->items->isEmpty()) {

            $itemToPack = $this->items->extract();

            //skip items that are simply too heavy
            if ($itemToPack->getWeight() > $remainingWeight) {
                continue;
            }

            $this->logger->debug("evaluating item {$itemToPack->getDescription()}");
            $this->logger->debug("remaining width: {$widthLeft}, length: {$lengthLeft}, depth: {$depthLeft}");
            $this->logger->debug("layerWidth: {$layerWidth}, layerLength: {$layerLength}, layerDepth: {$layerDepth}");

            $nextItem = !$this->items->isEmpty() ? $this->items->top() : null;
            $orientatedItem = $this->findBestOrientation($itemToPack, $nextItem, $widthLeft, $lengthLeft, $depthLeft);

            if ($orientatedItem) {

                $packedItems->insert($orientatedItem->getItem());
                $remainingWeight -= $itemToPack->getWeight();

                $lengthLeft -= $orientatedItem->getLength();
                $layerLength += $orientatedItem->getLength();
                $layerWidth = max($orientatedItem->getWidth(), $layerWidth);

                $layerDepth = max($layerDepth, $orientatedItem->getDepth()); //greater than 0, items will always be less deep

                //allow items to be stacked in place within the same footprint up to current layerdepth
                $maxStackDepth = $layerDepth - $orientatedItem->getDepth();
                while (!$this->items->isEmpty() && $this->canStackItemInLayer($itemToPack, $this->items->top(), $maxStackDepth, $remainingWeight)) {
                    $remainingWeight -= $this->items->top()->getWeight();
                    $maxStackDepth -= $this->items->top()->getDepth(); // XXX no attempt at best fit
                    $packedItems->insert($this->items->extract());
                }
            } else {
                if ($widthLeft >= min($itemToPack->getWidth(), $itemToPack->getLength()) && $this->isLayerStarted($layerWidth, $layerLength, $layerDepth)) {
                    $this->logger->debug("No more fit in lengthwise, resetting for new row");
                    $lengthLeft += $layerLength;
                    $widthLeft -= $layerWidth;
                    $layerWidth = $layerLength = 0;
                    $this->items->insert($itemToPack);
                    continue;
                } elseif ($lengthLeft < min($itemToPack->getWidth(), $itemToPack->getLength()) || $layerDepth == 0) {
                    $this->logger->debug("doesn't fit on layer even when empty");
                    continue;
                }

                $widthLeft = $layerWidth ? min(floor($layerWidth * 1.1), $this->box->getInnerWidth()) : $this->box->getInnerWidth();
                $lengthLeft = $layerLength ? min(floor($layerLength * 1.1), $this->box->getInnerLength()) : $this->box->getInnerLength();
                $depthLeft -= $layerDepth;

                $layerWidth = $layerLength = $layerDepth = 0;
                $this->logger->debug("doesn't fit, so starting next vertical layer");
                $this->items->insert($itemToPack);
            }
        }
        $this->logger->debug("done with this box");
        return new PackedBox($this->box, $packedItems, $widthLeft, $lengthLeft, $depthLeft, $remainingWeight);
    }

    /**
     * Figure out space left for next item if we pack this one in it's regular orientation
     * @param Item $item
     * @param int $widthLeft
     * @param int $lengthLeft
     * @return int
     */
    protected function fitsSameGap(Item $item, $widthLeft, $lengthLeft) {
        return min($widthLeft - $item->getWidth(), $lengthLeft - $item->getLength());
    }

    /**
     * Figure out space left for next item if we pack this one rotated by 90deg
     * @param Item $item
     * @param int $widthLeft
     * @param int $lengthLeft
     * @return int
     */
    protected function fitsRotatedGap(Item $item, $widthLeft, $lengthLeft) {
        return min($widthLeft - $item->getLength(), $lengthLeft - $item->getWidth());
    }

    /**
     * Get the best orientation for an item
     * @param Item $item
     * @param Item|null $nextItem
     * @param int $widthLeft
     * @param int $lengthLeft
     * @param int $depthLeft
     * @return bool|OrientatedItem
     */
    protected function findBestOrientation(Item $item, Item $nextItem = null, $widthLeft, $lengthLeft, $depthLeft) {

        $fitsSameGap = $this->fitsSameGap($item, $widthLeft, $lengthLeft);
        $fitsRotatedGap = $this->fitsRotatedGap($item, $widthLeft, $lengthLeft);
        $fitsDepth = $item->getDepth() <= $depthLeft;

        $fitsAtAll = $fitsDepth && ($fitsSameGap >= 0 || $fitsRotatedGap >= 0);

        if (!$fitsAtAll) {
            return false;
        }

        $betterUnRotated = !!($fitsRotatedGap < 0 ||
            ($fitsSameGap >= 0 && $fitsSameGap <= $fitsRotatedGap) ||
            ($item->getWidth() <= $widthLeft && $nextItem == $item && $lengthLeft >= 2 * $item->getLength()));

        if ($betterUnRotated) {
            $this->logger->debug("fits (better) unrotated");
            return new OrientatedItem($item, $item->getWidth(), $item->getLength(), $item->getDepth());
        } else {
            $this->logger->debug("fits (better) rotated");
            return new OrientatedItem($item, $item->getLength(), $item->getWidth(), $item->getDepth());
        }
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
