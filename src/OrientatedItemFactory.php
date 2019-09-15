<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
namespace DVDoug\BoxPacker;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

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
        $this->logger = new NullLogger();
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
     * @param int                 $rowLength
     * @param int                 $x
     * @param int                 $y
     * @param int                 $z
     * @param PackedItemList      $prevPackedItemList
     *
     * @return OrientatedItem|null
     */
    public function getBestOrientation(
        Item $item,
        OrientatedItem $prevItem = null,
        ItemList $nextItems,
        $isLastItem,
        $widthLeft,
        $lengthLeft,
        $depthLeft,
        $rowLength,
        $x,
        $y,
        $z,
        PackedItemList $prevPackedItemList
    ) {
        $possibleOrientations = $this->getPossibleOrientations($item, $prevItem, $widthLeft, $lengthLeft, $depthLeft, $x, $y, $z, $prevPackedItemList);
        $usableOrientations = $this->getUsableOrientations($item, $possibleOrientations, $isLastItem);

        if (empty($usableOrientations)) {
            return null;
        }

        usort($usableOrientations, function (OrientatedItem $a, OrientatedItem $b) use ($widthLeft, $lengthLeft, $depthLeft, $nextItems, $rowLength, $x, $y, $z, $prevPackedItemList) {
            $orientationAWidthLeft = $widthLeft - $a->getWidth();
            $orientationALengthLeft = $lengthLeft - $a->getLength();
            $orientationADepthLeft = $depthLeft - $a->getDepth();
            $orientationBWidthLeft = $widthLeft - $b->getWidth();
            $orientationBLengthLeft = $lengthLeft - $b->getLength();
            $orientationBDepthLeft = $depthLeft - $b->getDepth();

            $orientationAMinGap = min($orientationAWidthLeft, $orientationALengthLeft);
            $orientationBMinGap = min($orientationBWidthLeft, $orientationBLengthLeft);

            if ($orientationAMinGap === 0 && ($orientationBMinGap !== 0 || PHP_MAJOR_VERSION > 5)) { // prefer A if it leaves no gap
                return -1;
            }
            if ($orientationBMinGap === 0) { // prefer B if it leaves no gap
                return 1;
            }

            // prefer leaving room for next item in current row
            if ($nextItems->count()) {
                $nextItemFitA = count($this->getPossibleOrientations($nextItems->top(), $a, $orientationAWidthLeft, $lengthLeft, $depthLeft, $x, $y, $z, $prevPackedItemList));
                $nextItemFitB = count($this->getPossibleOrientations($nextItems->top(), $b, $orientationBWidthLeft, $lengthLeft, $depthLeft, $x, $y, $z, $prevPackedItemList));
                if ($nextItemFitA && !$nextItemFitB) {
                    return -1;
                }
                if ($nextItemFitB && !$nextItemFitA) {
                    return 1;
                }

                // if not an easy either/or, do a partial lookahead
                $additionalPackedA = $this->calculateAdditionalItemsPackedWithThisOrientation($a, $nextItems, $widthLeft, $lengthLeft, $depthLeft, $rowLength);
                $additionalPackedB = $this->calculateAdditionalItemsPackedWithThisOrientation($b, $nextItems, $widthLeft, $lengthLeft, $depthLeft, $rowLength);
                if ($additionalPackedA > $additionalPackedB) {
                    return -1;
                }
                if ($additionalPackedA < $additionalPackedB) {
                    return 1;
                }
                if ($additionalPackedA === 0) {
                    return PHP_MAJOR_VERSION > 5 ? -1 : 1;
                }
            }
            // otherwise prefer leaving minimum possible gap, or the greatest footprint
            return ($orientationADepthLeft - $orientationBDepthLeft) ?: ($orientationAMinGap - $orientationBMinGap) ?: ($a->getSurfaceFootprint() - $b->getSurfaceFootprint());
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
     * @param int                 $x
     * @param int                 $y
     * @param int                 $z
     * @param PackedItemList      $prevPackedItemList
     *
     * @return OrientatedItem[]
     */
    public function getPossibleOrientations(
        Item $item,
        OrientatedItem $prevItem = null,
        $widthLeft,
        $lengthLeft,
        $depthLeft,
        $x,
        $y,
        $z,
        PackedItemList $prevPackedItemList
    ) {
        $orientations = $orientationsDimensions = [];

        $isSame = false;
        if ($prevItem) {
            $itemADimensions = [$item->getWidth(), $item->getLength(), $item->getDepth()];
            $itemBDimensions = [$prevItem->getWidth(), $prevItem->getLength(), $prevItem->getDepth()];
            sort($itemADimensions);
            sort($itemBDimensions);
            $isSame = ($itemADimensions === $itemBDimensions);
        }

        //Special case items that are the same as what we just packed - keep orientation
        if ($isSame && $prevItem) {
            $orientationsDimensions[] = [$prevItem->getWidth(), $prevItem->getLength(), $prevItem->getDepth()];
        } else {
            //simple 2D rotation
            $orientationsDimensions[] = [$item->getWidth(), $item->getLength(), $item->getDepth()];
            $orientationsDimensions[] = [$item->getLength(), $item->getWidth(), $item->getDepth()];
        }

        //remove any that simply don't fit
        $orientationsDimensions = array_unique($orientationsDimensions, SORT_REGULAR);
        $orientationsDimensions = array_filter($orientationsDimensions, static function (array $i) use ($widthLeft, $lengthLeft, $depthLeft) {
            return $i[0] <= $widthLeft && $i[1] <= $lengthLeft && $i[2] <= $depthLeft;
        });

        foreach ($orientationsDimensions as $dimensions) {
            $orientations[] = new OrientatedItem($item, $dimensions[0], $dimensions[1], $dimensions[2]);
        }

        if ($item instanceof ConstrainedPlacementItem) {
            $box = $this->box;
            $orientations = array_filter($orientations, static function (OrientatedItem $i) use ($box, $x, $y, $z, $prevPackedItemList) {
                /** @var ConstrainedPlacementItem $constrainedItem */
                $constrainedItem = $i->getItem();

                return $constrainedItem->canBePacked($box, $prevPackedItemList, $x, $y, $z, $i->getWidth(), $i->getLength(), $i->getDepth());
            });
        }

        return $orientations;
    }

    /**
     * @param  Item             $item
     * @return OrientatedItem[]
     */
    public function getPossibleOrientationsInEmptyBox(Item $item)
    {
        $cacheKey = $item->getWidth() .
            '|' .
            $item->getLength() .
            '|' .
            $item->getDepth() .
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
                $this->box->getInnerDepth(),
                0,
                0,
                0,
                new PackedItemList()
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
    protected function getStableOrientationsInEmptyBox(Item $item)
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
    public function isSameDimensions(Item $itemA, Item $itemB)
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
     * @param  int            $currentRowLengthBeforePacking
     * @return int
     */
    protected function calculateAdditionalItemsPackedWithThisOrientation(
        OrientatedItem $prevItem,
        ItemList $nextItems,
        $originalWidthLeft,
        $originalLengthLeft,
        $depthLeft,
        $currentRowLengthBeforePacking
    ) {
        $packedCount = 0;

        $currentRowLength = max($prevItem->getLength(), $currentRowLengthBeforePacking);

        $itemsToPack = $nextItems->topN(8); // cap lookahead as this gets recursive and slow

        $tempBox = new WorkingVolume($originalWidthLeft - $prevItem->getWidth(), $currentRowLength, $depthLeft, PHP_INT_MAX);
        $tempPacker = new VolumePacker($tempBox, clone $itemsToPack);
        $tempPacker->setLookAheadMode(true);
        $remainingRowPacked = $tempPacker->pack();
        /** @var PackedItem $packedItem */
        foreach ($remainingRowPacked->getItems() as $packedItem) {
            $itemsToPack->remove($packedItem);
        }

        $tempBox = new WorkingVolume($originalWidthLeft, $originalLengthLeft - $currentRowLength, $depthLeft, PHP_INT_MAX);
        $tempPacker = new VolumePacker($tempBox, clone $itemsToPack);
        $tempPacker->setLookAheadMode(true);
        $nextRowsPacked = $tempPacker->pack();
        /** @var PackedItem $packedItem */
        foreach ($nextRowsPacked->getItems() as $packedItem) {
            $itemsToPack->remove($packedItem);
        }

        $this->logger->debug('Lookahead with orientation', ['packedCount' => $packedCount, 'orientatedItem' => $prevItem]);

        return $nextItems->count() - $itemsToPack->count();
    }
}
