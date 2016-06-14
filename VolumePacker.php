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
        $this->logger->log(LogLevel::DEBUG, "[EVALUATING BOX] {$this->box->getReference()}");

        $packedItems = new ItemList;
        $remainingDepth = $this->box->getInnerDepth();
        $remainingWeight = $this->box->getMaxWeight() - $this->box->getEmptyWeight();
        $remainingWidth = $this->box->getInnerWidth();
        $remainingLength = $this->box->getInnerLength();

        $layerWidth = $layerLength = $layerDepth = 0;
        while (!$this->items->isEmpty()) {

            $itemToPack = $this->items->extract();

            //skip items that are simply too heavy
            if ($itemToPack->getWeight() > $remainingWeight) {
                continue;
            }

            $this->logger->debug("evaluating item {$itemToPack->getDescription()}");
            $this->logger->debug("remaining width: {$remainingWidth}, length: {$remainingLength}, depth: {$remainingDepth}");
            $this->logger->debug("layerWidth: {$layerWidth}, layerLength: {$layerLength}, layerDepth: {$layerDepth}");

            $nextItem = !$this->items->isEmpty() ? $this->items->top() : null;
            $orientatedItem = $this->findBestOrientation($itemToPack, $nextItem, $remainingWidth, $remainingLength, $remainingDepth);

            if ($orientatedItem) {

                $packedItems->insert($orientatedItem->getItem());
                $remainingWeight -= $itemToPack->getWeight();

                $remainingLength -= $orientatedItem->getLength();
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
                if ($remainingWidth >= min($itemToPack->getWidth(), $itemToPack->getLength()) && $this->isLayerStarted($layerWidth, $layerLength, $layerDepth)) {
                    $this->logger->debug("No more fit in lengthwise, resetting for new row");
                    $remainingLength += $layerLength;
                    $remainingWidth -= $layerWidth;
                    $layerWidth = $layerLength = 0;
                    $this->items->insert($itemToPack);
                    continue;
                } elseif ($remainingLength < min($itemToPack->getWidth(), $itemToPack->getLength()) || $layerDepth == 0) {
                    $this->logger->debug("doesn't fit on layer even when empty");
                    continue;
                }

                $remainingWidth = $layerWidth ? min(floor($layerWidth * 1.1), $this->box->getInnerWidth()) : $this->box->getInnerWidth();
                $remainingLength = $layerLength ? min(floor($layerLength * 1.1), $this->box->getInnerLength()) : $this->box->getInnerLength();
                $remainingDepth -= $layerDepth;

                $layerWidth = $layerLength = $layerDepth = 0;
                $this->logger->log(LogLevel::DEBUG, "doesn't fit, so starting next vertical layer");
                $this->items->insert($itemToPack);
            }
        }
        $this->logger->log(LogLevel::DEBUG, "done with this box");
        return new PackedBox($this->box, $packedItems, $remainingWidth, $remainingLength, $remainingDepth, $remainingWeight);
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
     * Get the best orientation for an item
     * @param Item $item
     * @param Item|null $nextItem
     * @param int $remainingWidth
     * @param int $remainingLength
     * @param int $remainingDepth
     * @return bool|OrientatedItem
     */
    protected function findBestOrientation(Item $item, Item $nextItem = null, $remainingWidth, $remainingLength, $remainingDepth) {

        $fitsSameGap = $this->fitsSameGap($item, $remainingWidth, $remainingLength);
        $fitsRotatedGap = $this->fitsRotatedGap($item, $remainingWidth, $remainingLength);
        $fitsDepth = $item->getDepth() <= $remainingDepth;

        $fitsAtAll = $fitsDepth && ($fitsSameGap >= 0 || $fitsRotatedGap >= 0);

        if (!$fitsAtAll) {
            return false;
        }

        $betterUnRotated = !!($fitsRotatedGap < 0 ||
            ($fitsSameGap >= 0 && $fitsSameGap <= $fitsRotatedGap) ||
            ($item->getWidth() <= $remainingWidth && $nextItem == $item && $remainingLength >= 2 * $item->getLength()));

        if ($betterUnRotated) {
            $this->logger->debug("fits (better) unrotated");
            return new OrientatedItem($item, $item->getWidth(), $item->getLength(), $item->getDepth());
        } else {
            $this->logger->debug("fits (better) rotated");
            return new OrientatedItem($item, $item->getLength(), $item->getWidth(), $item->getDepth());
        }
    }

    /**
     * @param Item $item
     * @param Item|null $nextItem
     * @param $remainingWidth
     * @param $remainingLength
     * @return bool
     */
    protected function fitsBetterUnrotated(Item $item, Item $nextItem = null, $remainingWidth, $remainingLength) {

        $fitsSameGap = $this->fitsSameGap($item, $remainingWidth, $remainingLength);
        $fitsRotatedGap = $this->fitsRotatedGap($item, $remainingWidth, $remainingLength);

        return !!($fitsRotatedGap < 0 ||
        ($fitsSameGap >= 0 && $fitsSameGap <= $fitsRotatedGap) ||
        ($item->getWidth() <= $remainingWidth && $nextItem == $item && $remainingLength >= 2 * $item->getLength()));
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
