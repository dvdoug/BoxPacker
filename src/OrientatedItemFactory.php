<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use function array_filter;
use function count;
use function min;
use function reset;
use function sort;
use function usort;

/**
 * Figure out orientations for an item and a given set of dimensions.
 *
 * @author Doug Wright
 * @internal
 */
class OrientatedItemFactory implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var Box */
    protected $box;

    /**
     * @var OrientatedItem[]
     */
    protected static $emptyBoxCache = [];

    public function __construct(Box $box)
    {
        $this->box = $box;
    }

    /**
     * Get the best orientation for an item.
     *
     * @param Item                $item
     * @param OrientatedItem|null $prevItem
     * @param ItemList            $nextItems
     * @param bool                $isLastItem
     * @param int                 $widthLeft
     * @param int                 $lengthLeft
     * @param int                 $depthLeft
     *
     * @return OrientatedItem|null
     */
    public function getBestOrientation(
        Item $item,
        ?OrientatedItem $prevItem,
        ItemList $nextItems,
        bool $isLastItem,
        int $widthLeft,
        int $lengthLeft,
        int $depthLeft
    ): ?OrientatedItem {
        $possibleOrientations = $this->getPossibleOrientations($item, $prevItem, $widthLeft, $lengthLeft, $depthLeft);
        $usableOrientations = $this->getUsableOrientations($item, $possibleOrientations, $isLastItem);

        if (empty($usableOrientations)) {
            return null;
        }

        usort($usableOrientations, function (OrientatedItem $a, OrientatedItem $b) use ($widthLeft, $lengthLeft, $depthLeft, $nextItems) {
            $orientationAWidthLeft = $widthLeft - $a->getWidth();
            $orientationALengthLeft = $lengthLeft - $a->getLength();
            $orientationADepthLeft = $depthLeft - $a->getDepth();
            $orientationBWidthLeft = $widthLeft - $b->getWidth();
            $orientationBLengthLeft = $lengthLeft - $b->getLength();
            $orientationBDepthLeft = $depthLeft - $b->getDepth();

            $orientationAMinGap = min($orientationAWidthLeft, $orientationALengthLeft);
            $orientationBMinGap = min($orientationBWidthLeft, $orientationBLengthLeft);

            if ($orientationAMinGap === 0) { // prefer A if it leaves no gap
                return -1;
            }
            if ($orientationBMinGap === 0) { // prefer B if it leaves no gap
                return 1;
            }

            // prefer leaving room for next item in current row
            if ($nextItems->count()) {
                $nextItemFitA = count($this->getPossibleOrientations($nextItems->top(), $a, $orientationAWidthLeft, $lengthLeft, $depthLeft));
                $nextItemFitB = count($this->getPossibleOrientations($nextItems->top(), $b, $orientationBWidthLeft, $lengthLeft, $depthLeft));
                if ($nextItemFitA && !$nextItemFitB) {
                    return -1;
                }
                if ($nextItemFitB && !$nextItemFitA) {
                    return 1;
                }

                // if not an easy either/or, do a partial lookahead
                $additionalPackedA = $this->calculateAdditionalItemsPackedWithThisOrientation($a, $nextItems, $widthLeft, $lengthLeft, $depthLeft);
                $additionalPackedB = $this->calculateAdditionalItemsPackedWithThisOrientation($b, $nextItems, $widthLeft, $lengthLeft, $depthLeft);
                if ($additionalPackedA !== $additionalPackedB) {
                    return $additionalPackedB <=> $additionalPackedA;
                }
            }
            // otherwise prefer leaving minimum possible gap, or the greatest footprint
            return $orientationADepthLeft <=> $orientationBDepthLeft ?: $orientationAMinGap <=> $orientationBMinGap ?: $a->getSurfaceFootprint() <=> $b->getSurfaceFootprint();
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
        ?OrientatedItem $prevItem,
        int $widthLeft,
        int $lengthLeft,
        int $depthLeft
    ): array {
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
     * @param  Item             $item
     * @return OrientatedItem[]
     */
    public function getPossibleOrientationsInEmptyBox(Item $item): array
    {
        $cacheKey = $item->getWidth() .
            '|' .
            $item->getLength() .
            '|' .
            $item->getDepth() .
            '|' .
            ($item->getKeepFlat() ? '2D' : '3D') .
            '|' .
            $this->box->getInnerWidth() .
            '|' .
            $this->box->getInnerLength() .
            '|' .
            $this->box->getInnerDepth();

        if (isset(static::$emptyBoxCache[$cacheKey])) {
            $orientations = static::$emptyBoxCache[$cacheKey];
        } else {
            $orientations = $this->getPossibleOrientations(
                $item,
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
     * @param Item             $item
     * @param OrientatedItem[] $possibleOrientations
     * @param bool             $isLastItem
     *
     * @return OrientatedItem[]
     */
    protected function getUsableOrientations(
        Item $item,
        $possibleOrientations,
        bool $isLastItem
    ): array {
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
            $stableOrientationsInEmptyBox = $this->getStableOrientationsInEmptyBox($item);

            if ($isLastItem || count($stableOrientationsInEmptyBox) === 0) {
                $orientationsToUse = $unstableOrientations;
            }
        }

        return $orientationsToUse;
    }

    /**
     * Return the orientations for this item if it were to be placed into the box with nothing else.
     *
     * @param  Item  $item
     * @return array
     */
    protected function getStableOrientationsInEmptyBox(Item $item): array
    {
        $orientationsInEmptyBox = $this->getPossibleOrientationsInEmptyBox($item);

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
    protected function isSameDimensions(Item $itemA, Item $itemB): bool
    {
        $itemADimensions = [$itemA->getWidth(), $itemA->getLength(), $itemA->getDepth()];
        $itemBDimensions = [$itemB->getWidth(), $itemB->getLength(), $itemB->getDepth()];
        sort($itemADimensions);
        sort($itemBDimensions);

        return $itemADimensions === $itemBDimensions;
    }

    /**
     * Approximation of a forward-looking packing.
     *
     * Not an actual packing, that has additional logic regarding constraints and stackability, this focuses
     * purely on fit.
     *
     * @param  OrientatedItem $prevItem
     * @param  ItemList       $nextItems
     * @param  int            $originalWidthLeft
     * @param  int            $originalLengthLeft
     * @param  int            $depthLeft
     * @return int
     */
    protected function calculateAdditionalItemsPackedWithThisOrientation(
        OrientatedItem $prevItem,
        ItemList $nextItems,
        int $originalWidthLeft,
        int $originalLengthLeft,
        int $depthLeft
    ): int {
        $packedCount = 0;

        // first try packing into current row
        $currentRowWorkingSetItems = clone $nextItems;
        $nextRowWorkingSetItems = new ItemList();
        $widthLeft = $originalWidthLeft - $prevItem->getWidth();
        $lengthLeft = $originalLengthLeft;
        while (count($currentRowWorkingSetItems) > 0 && $widthLeft > 0) {
            $itemToPack = $currentRowWorkingSetItems->extract();
            $orientatedItem = $this->getBestOrientation($itemToPack, $prevItem, $currentRowWorkingSetItems, !count($currentRowWorkingSetItems), $widthLeft, $lengthLeft, $depthLeft);
            if ($orientatedItem instanceof OrientatedItem) {
                ++$packedCount;
                $widthLeft -= $orientatedItem->getWidth();
                $prevItem = $orientatedItem;
            } else {
                $nextRowWorkingSetItems->insert($itemToPack);
            }
        }

        // then see what happens if we try in the next row
        $widthLeft = $originalWidthLeft;
        $lengthLeft = $originalLengthLeft - $prevItem->getLength();
        while (count($nextRowWorkingSetItems) > 0 && $widthLeft > 0) {
            $itemToPack = $nextRowWorkingSetItems->extract();
            $orientatedItem = $this->getBestOrientation($itemToPack, $prevItem, $nextRowWorkingSetItems, !count($nextRowWorkingSetItems), $widthLeft, $lengthLeft, $depthLeft);
            if ($orientatedItem instanceof OrientatedItem) {
                ++$packedCount;
                $widthLeft -= $orientatedItem->getWidth();
                $prevItem = $orientatedItem;
            }
        }

        return $packedCount; // this isn't scientific, but is a reasonable proxy for success from an actual forward packing
    }
}
