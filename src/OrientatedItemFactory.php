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

    /**
     * @var OrientatedItem[]
     */
    protected static $emptyBoxCache = [];

    /**
     * Get the best orientation for an item.
     *
     * @param Box             $box
     * @param Item            $item
     * @param PackedItem|null $prevItem
     * @param bool            $isLastItem
     * @param int             $widthLeft
     * @param int             $lengthLeft
     * @param int             $depthLeft
     *
     * @return OrientatedItem|false
     */
    public function getBestOrientation(Box $box, Item $item, PackedItem $prevItem = null, $isLastItem, $widthLeft, $lengthLeft, $depthLeft)
    {
        $possibleOrientations = $this->getPossibleOrientations($item, $prevItem, $widthLeft, $lengthLeft, $depthLeft);
        $usableOrientations = $this->getUsableOrientations($possibleOrientations, $box, $item, $isLastItem);

        $orientationFits = [];
        /** @var OrientatedItem $orientation */
        foreach ($usableOrientations as $o => $orientation) {
            $orientationFit = min($widthLeft - $orientation->getWidth(), $lengthLeft - $orientation->getLength());
            $orientationFits[$o] = $orientationFit;
        }

        if (!empty($orientationFits)) {
            asort($orientationFits);
            reset($orientationFits);
            $bestFit = $usableOrientations[key($orientationFits)];
            $this->logger->debug('Selected best fit orientation', ['orientation' => $bestFit]);

            return $bestFit;
        } else {
            return false;
        }
    }

    /**
     * Find all possible orientations for an item.
     *
     * @param Item            $item
     * @param PackedItem|null $prevItem
     * @param int             $widthLeft
     * @param int             $lengthLeft
     * @param int             $depthLeft
     *
     * @return OrientatedItem[]
     */
    public function getPossibleOrientations(Item $item, PackedItem $prevItem = null, $widthLeft, $lengthLeft, $depthLeft)
    {
        $orientations = [];

        //Special case items that are the same as what we just packed - keep orientation
        /* @noinspection PhpNonStrictObjectEqualityInspection */
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
     * @param Item $item
     * @param Box  $box
     *
     * @return OrientatedItem[]
     */
    public function getPossibleOrientationsInEmptyBox(Item $item, Box $box)
    {
        $cacheKey = $item->getWidth().
            '|'.
            $item->getLength().
            '|'.
            $item->getDepth().
            '|'.
            ($item->getKeepFlat() ? '2D' : '3D').
            '|'.
            $box->getInnerWidth().
            '|'.
            $box->getInnerLength().
            '|'.
            $box->getInnerDepth();

        if (isset(static::$emptyBoxCache[$cacheKey])) {
            $orientations = static::$emptyBoxCache[$cacheKey];
        } else {
            $orientations = $this->getPossibleOrientations(
                $item,
                null,
                $box->getInnerWidth(),
                $box->getInnerLength(),
                $box->getInnerDepth()
            );
            static::$emptyBoxCache[$cacheKey] = $orientations;
        }

        return $orientations;
    }

    /**
     * @param OrientatedItem[] $possibleOrientations
     * @param Box              $box
     * @param Item             $item
     * @param bool             $isLastItem
     *
     * @return OrientatedItem[]
     */
    protected function getUsableOrientations(
        $possibleOrientations,
        Box $box,
        Item $item,
        $isLastItem
    ) {
        /*
         * Divide possible orientations into stable (low centre of gravity) and unstable (high centre of gravity)
         */
        $stableOrientations = [];
        $unstableOrientations = [];

        foreach ($possibleOrientations as $o => $orientation) {
            if ($orientation->isStable()) {
                $stableOrientations[] = $orientation;
            } else {
                $unstableOrientations[] = $orientation;
            }
        }

        $orientationsToUse = [];

        /*
         * We prefer to use stable orientations only, but allow unstable ones if either
         * the item is the last one left to pack OR
         * the item doesn't fit in the box any other way
         */
        if (count($stableOrientations) > 0) {
            $orientationsToUse = $stableOrientations;
        } elseif (count($unstableOrientations) > 0) {
            $orientationsInEmptyBox = $this->getPossibleOrientationsInEmptyBox($item, $box);

            $stableOrientationsInEmptyBox = array_filter(
                $orientationsInEmptyBox,
                function (OrientatedItem $orientation) {
                    return $orientation->isStable();
                }
            );

            if ($isLastItem || count($stableOrientationsInEmptyBox) == 0) {
                $orientationsToUse = $unstableOrientations;
            }
        }

        return $orientationsToUse;
    }
}
