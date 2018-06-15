<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Figure out orientations for an item and a given set of dimensions.
 *
 * @author Doug Wright
 */
class OrientatedItemFactory implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var Item */
    protected $item;

    /** @var Box */
    protected $box;

    /**
     * @var OrientatedItem[]
     */
    protected static $emptyBoxCache = [];

    public function __construct(Item $item, Box $box)
    {
        $this->item = $item;
        $this->box = $box;
    }

    /**
     * Get the best orientation for an item.
     *
     * @param OrientatedItem|null $prevItem
     * @param Item|null           $nextItem
     * @param bool                $isLastItem
     * @param int                 $widthLeft
     * @param int                 $lengthLeft
     * @param int                 $depthLeft
     *
     * @return OrientatedItem|null
     */
    public function getBestOrientation(
        OrientatedItem $prevItem = null,
        Item $nextItem = null,
        $isLastItem,
        $widthLeft,
        $lengthLeft,
        $depthLeft
    ) {
        $possibleOrientations = $this->getPossibleOrientations($this->item, $prevItem, $widthLeft, $lengthLeft, $depthLeft);
        $usableOrientations = $this->getUsableOrientations($possibleOrientations, $isLastItem);

        if (empty($usableOrientations)) {
            return;
        }

        usort($usableOrientations, function (OrientatedItem $a, OrientatedItem $b) use ($widthLeft, $lengthLeft, $depthLeft, $nextItem) {
            $orientationAWidthLeft = $widthLeft - $a->getWidth();
            $orientationALengthLeft = $lengthLeft - $a->getLength();
            $orientationBWidthLeft = $widthLeft - $b->getWidth();
            $orientationBLengthLeft = $lengthLeft - $b->getLength();

            $orientationAMinGap = min($orientationAWidthLeft, $orientationALengthLeft);
            $orientationBMinGap = min($orientationBWidthLeft, $orientationBLengthLeft);

            if ($orientationAMinGap === 0) { // prefer A if it leaves no gap
                return -1;
            } elseif ($orientationBMinGap === 0) { // prefer B if it leaves no gap
                return 1;
            } else { // prefer leaving room for next item in current row
                if ($nextItem) {
                    $nextItemFitA = count($this->getPossibleOrientations($nextItem, $a, $orientationAWidthLeft, $orientationALengthLeft, $depthLeft));
                    $nextItemFitB = count($this->getPossibleOrientations($nextItem, $b, $orientationBWidthLeft, $orientationBLengthLeft, $depthLeft));
                    if ($nextItemFitA && !$nextItemFitB) {
                        return -1;
                    } elseif ($nextItemFitB && !$nextItemFitA) {
                        return 1;
                    }
                }
                // otherwise prefer leaving minimum possible gap
                return min($orientationAWidthLeft, $orientationALengthLeft) - min($orientationBWidthLeft, $orientationBLengthLeft);
            }
        });

        $bestFit = reset($usableOrientations);
        $this->logger->debug('Selected best fit orientation', ['orientation' => $bestFit]);

        return $bestFit;
    }

    /**
     * Find all possible orientations for an item.
     *
     * @param Item                $item
     * @param OrientatedItem|null $prevItem
     * @param int                 $widthLeft
     * @param int                 $lengthLeft
     * @param int                 $depthLeft
     *
     * @return OrientatedItem[]
     */
    public function getPossibleOrientations(
        Item $item,
        OrientatedItem $prevItem = null,
        $widthLeft,
        $lengthLeft,
        $depthLeft
    ) {
        $orientations = [];

        //Special case items that are the same as what we just packed - keep orientation
        if ($prevItem && $this->isSameDimensions($prevItem->getItem(), $item)) {
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
     * @return OrientatedItem[]
     */
    public function getPossibleOrientationsInEmptyBox()
    {
        $cacheKey = $this->item->getWidth().
            '|'.
            $this->item->getLength().
            '|'.
            $this->item->getDepth().
            '|'.
            ($this->item->getKeepFlat() ? '2D' : '3D').
            '|'.
            $this->box->getInnerWidth().
            '|'.
            $this->box->getInnerLength().
            '|'.
            $this->box->getInnerDepth();

        if (isset(static::$emptyBoxCache[$cacheKey])) {
            $orientations = static::$emptyBoxCache[$cacheKey];
        } else {
            $orientations = $this->getPossibleOrientations(
                $this->item,
                null,
                $this->box->getInnerWidth(),
                $this->box->getInnerLength(),
                $this->box->getInnerDepth()
            );
            static::$emptyBoxCache[$cacheKey] = $orientations;
        }

        return $orientations;
    }

    /**
     * @param OrientatedItem[] $possibleOrientations
     * @param bool             $isLastItem
     *
     * @return OrientatedItem[]
     */
    protected function getUsableOrientations(
        $possibleOrientations,
        $isLastItem
    ) {
        $orientationsToUse = $stableOrientations = $unstableOrientations = [];

        // Divide possible orientations into stable (low centre of gravity) and unstable (high centre of gravity)
        foreach ($possibleOrientations as $orientation) {
            if ($orientation->isStable()) {
                $stableOrientations[] = $orientation;
            } else {
                $unstableOrientations[] = $orientation;
            }
        }

        /*
         * We prefer to use stable orientations only, but allow unstable ones if either
         * the item is the last one left to pack OR
         * the item doesn't fit in the box any other way
         */
        if (count($stableOrientations) > 0) {
            $orientationsToUse = $stableOrientations;
        } elseif (count($unstableOrientations) > 0) {
            $stableOrientationsInEmptyBox = $this->getStableOrientationsInEmptyBox();

            if ($isLastItem || count($stableOrientationsInEmptyBox) == 0) {
                $orientationsToUse = $unstableOrientations;
            }
        }

        return $orientationsToUse;
    }

    /**
     * Return the orientations for this item if it were to be placed into the box with nothing else.
     *
     * @return array
     */
    protected function getStableOrientationsInEmptyBox()
    {
        $orientationsInEmptyBox = $this->getPossibleOrientationsInEmptyBox();

        return array_filter(
            $orientationsInEmptyBox,
            function (OrientatedItem $orientation) {
                return $orientation->isStable();
            }
        );
    }

    /**
     * Compare two items to see if they have same dimensions.
     *
     * @param Item $itemA
     * @param Item $itemB
     *
     * @return bool
     */
    protected function isSameDimensions(Item $itemA, Item $itemB)
    {
        $itemADimensions = [$itemA->getWidth(), $itemA->getLength(), $itemA->getDepth()];
        $itemBDimensions = [$itemB->getWidth(), $itemB->getLength(), $itemB->getDepth()];
        sort($itemADimensions);
        sort($itemBDimensions);

        return $itemADimensions === $itemBDimensions;
    }
}
