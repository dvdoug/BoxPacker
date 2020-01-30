<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use function array_filter;
use function count;
use function min;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
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

    /**
     * @var int[]
     */
    protected static $lookaheadCache = [];

    public function __construct(Box $box)
    {
        $this->box = $box;
        $this->logger = new NullLogger();
    }

    /**
     * Get the best orientation for an item.
     */
    public function getBestOrientation(
        Item $item,
        ?OrientatedItem $prevItem,
        ItemList $nextItems,
        bool $isLastItem,
        int $widthLeft,
        int $lengthLeft,
        int $depthLeft,
        int $rowLength,
        int $x,
        int $y,
        int $z,
        PackedItemList $prevPackedItemList
    ): ?OrientatedItem {
        $possibleOrientations = $this->getPossibleOrientations($item, $prevItem, $widthLeft, $lengthLeft, $depthLeft, $x, $y, $z, $prevPackedItemList);
        $usableOrientations = $this->getUsableOrientations($item, $possibleOrientations, $isLastItem);

        if (empty($usableOrientations)) {
            return null;
        }

        usort($usableOrientations, function (OrientatedItem $a, OrientatedItem $b) use ($widthLeft, $lengthLeft, $depthLeft, $nextItems, $rowLength, $x, $y, $z, $prevPackedItemList) {
            $orientationAWidthLeft = $widthLeft - $a->getWidth();
            $orientationALengthLeft = $lengthLeft - $a->getLength();
            $orientationBWidthLeft = $widthLeft - $b->getWidth();
            $orientationBLengthLeft = $lengthLeft - $b->getLength();

            $orientationAMinGap = min($orientationAWidthLeft, $orientationALengthLeft);
            $orientationBMinGap = min($orientationBWidthLeft, $orientationBLengthLeft);

            if ($orientationAMinGap === 0 && $orientationBMinGap === 0) {
                return $a->getDepth() <=> $b->getDepth();
            }
            if ($orientationAMinGap === 0) { // prefer A if it leaves no gap
                return -1;
            }
            if ($orientationBMinGap === 0) { // prefer B if it leaves no gap
                return 1;
            }

            // prefer leaving room for next item in current row
            if ($nextItems->count()) {
                $nextItemFitA = $this->getPossibleOrientations($nextItems->top(), $a, $orientationAWidthLeft, $lengthLeft, $depthLeft, $x, $y, $z, $prevPackedItemList);
                $nextItemFitB = $this->getPossibleOrientations($nextItems->top(), $b, $orientationBWidthLeft, $lengthLeft, $depthLeft, $x, $y, $z, $prevPackedItemList);
                if ($nextItemFitA && !$nextItemFitB) {
                    return -1;
                }
                if ($nextItemFitB && !$nextItemFitA) {
                    return 1;
                }

                // if not an easy either/or, do a partial lookahead
                $additionalPackedA = $this->calculateAdditionalItemsPackedWithThisOrientation($a, $nextItems, $widthLeft, $lengthLeft, $depthLeft, $rowLength);
                $additionalPackedB = $this->calculateAdditionalItemsPackedWithThisOrientation($b, $nextItems, $widthLeft, $lengthLeft, $depthLeft, $rowLength);
                if ($additionalPackedA !== $additionalPackedB) {
                    return $additionalPackedB <=> $additionalPackedA;
                }
            }
            // otherwise prefer leaving minimum possible gap, or the greatest footprint
            return $orientationAMinGap <=> $orientationBMinGap ?: $a->getSurfaceFootprint() <=> $b->getSurfaceFootprint();
        });

        $bestFit = reset($usableOrientations);
        $this->logger->debug('Selected best fit orientation', ['orientation' => $bestFit]);

        return $bestFit;
    }

    /**
     * Find all possible orientations for an item.
     *
     * @return OrientatedItem[]
     */
    public function getPossibleOrientations(
        Item $item,
        ?OrientatedItem $prevItem,
        int $widthLeft,
        int $lengthLeft,
        int $depthLeft,
        int $x,
        int $y,
        int $z,
        PackedItemList $prevPackedItemList
    ): array {
        $orientations = $orientationsDimensions = [];

        $isSame = false;
        if ($prevItem) {
            if ($item === $prevItem->getItem()) {
                $isSame = true;
            } else {
                $itemADimensions = [$item->getWidth(), $item->getLength(), $item->getDepth()];
                $itemBDimensions = [$prevItem->getWidth(), $prevItem->getLength(), $prevItem->getDepth()];
                sort($itemADimensions);
                sort($itemBDimensions);
                $isSame = ($itemADimensions === $itemBDimensions);
            }
        }

        //Special case items that are the same as what we just packed - keep orientation
        if ($isSame && $prevItem) {
            $orientationsDimensions[] = [$prevItem->getWidth(), $prevItem->getLength(), $prevItem->getDepth()];
        } else {
            //simple 2D rotation
            $orientationsDimensions[] = [$item->getWidth(), $item->getLength(), $item->getDepth()];
            $orientationsDimensions[] = [$item->getLength(), $item->getWidth(), $item->getDepth()];

            //add 3D rotation if we're allowed
            if (!$item->getKeepFlat()) {
                $orientationsDimensions[] = [$item->getWidth(), $item->getDepth(), $item->getLength()];
                $orientationsDimensions[] = [$item->getLength(), $item->getDepth(), $item->getWidth()];
                $orientationsDimensions[] = [$item->getDepth(), $item->getWidth(), $item->getLength()];
                $orientationsDimensions[] = [$item->getDepth(), $item->getLength(), $item->getWidth()];
            }
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
                return $i->getItem()->canBePacked($box, $prevPackedItemList, $x, $y, $z, $i->getWidth(), $i->getLength(), $i->getDepth());
            });
        }

        return $orientations;
    }

    /**
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
     * @param OrientatedItem[] $possibleOrientations
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
     * Approximation of a forward-looking packing.
     *
     * Not an actual packing, that has additional logic regarding constraints and stackability, this focuses
     * purely on fit.
     */
    protected function calculateAdditionalItemsPackedWithThisOrientation(
        OrientatedItem $prevItem,
        ItemList $nextItems,
        int $originalWidthLeft,
        int $originalLengthLeft,
        int $depthLeft,
        int $currentRowLengthBeforePacking
    ): int {
        $currentRowLength = max($prevItem->getLength(), $currentRowLengthBeforePacking);

        $itemsToPack = $nextItems->topN(8); // cap lookahead as this gets recursive and slow

        $cacheKey = $originalWidthLeft .
            '|' .
            $originalLengthLeft .
            '|' .
            $prevItem->getWidth() .
            '|' .
            $prevItem->getLength() .
            '|' .
            $currentRowLength .
            '|'
            . $depthLeft;

        /** @var Item $itemToPack */
        foreach ($itemsToPack as $itemToPack) {
            $cacheKey .= '|' .
                $itemToPack->getWidth() .
                '|' .
                $itemToPack->getLength() .
                '|' .
                $itemToPack->getDepth() .
                '|' .
                $itemToPack->getWeight() .
                '|' .
                ($itemToPack->getKeepFlat() ? '1' : '0');
        }

        if (!isset(static::$lookaheadCache[$cacheKey])) {
            $tempBox = new WorkingVolume($originalWidthLeft - $prevItem->getWidth(), $currentRowLength, $depthLeft, PHP_INT_MAX);
            $tempPacker = new VolumePacker($tempBox, clone $itemsToPack);
            $tempPacker->setLookAheadMode(true);
            $remainingRowPacked = $tempPacker->pack();
            /** @var PackedItem $packedItem */
            foreach ($remainingRowPacked->getItems() as $packedItem) {
                $itemsToPack->remove($packedItem->getItem());
            }

            $tempBox = new WorkingVolume($originalWidthLeft, $originalLengthLeft - $currentRowLength, $depthLeft, PHP_INT_MAX);
            $tempPacker = new VolumePacker($tempBox, clone $itemsToPack);
            $tempPacker->setLookAheadMode(true);
            $nextRowsPacked = $tempPacker->pack();
            /** @var PackedItem $packedItem */
            foreach ($nextRowsPacked->getItems() as $packedItem) {
                $itemsToPack->remove($packedItem->getItem());
            }

            $packedCount = $nextItems->count() - $itemsToPack->count();
            $this->logger->debug('Lookahead with orientation', ['packedCount' => $packedCount, 'orientatedItem' => $prevItem]);

            static::$lookaheadCache[$cacheKey] = $packedCount;
        }

        return static::$lookaheadCache[$cacheKey];
    }
}
