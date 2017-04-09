<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
namespace DVDoug\BoxPacker;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Figure out orientations for an item and a given set of dimensions
 * @author Doug Wright
 * @package BoxPacker
 */
class OrientatedItemFactory implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Get the best orientation for an item
     * @param Box $box
     * @param Item $item
     * @param OrientatedItem|null $prevItem
     * @param Item|null $nextItem
     * @param int $widthLeft
     * @param int $lengthLeft
     * @param int $depthLeft
     * @return OrientatedItem|false
     */
    public function getBestOrientation(Box $box, Item $item, OrientatedItem $prevItem = null, Item $nextItem = null, $widthLeft, $lengthLeft, $depthLeft) {

        $possibleOrientations = $this->getPossibleOrientations($item, $prevItem, $widthLeft, $lengthLeft, $depthLeft);
        $usableOrientations = $this->getUsableOrientations($possibleOrientations, $box, $item, $prevItem, $nextItem);

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
            $this->logger->debug("Selected best fit orientation", ['orientation' => $bestFit]);
            return $bestFit;
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
    public function getPossibleOrientations(Item $item, OrientatedItem $prevItem = null, $widthLeft, $lengthLeft, $depthLeft) {

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
        return array_filter($orientations, function(OrientatedItem $i) use ($widthLeft, $lengthLeft, $depthLeft) {
            return $i->getWidth() <= $widthLeft && $i->getLength() <= $lengthLeft && $i->getDepth() <= $depthLeft;
        });
    }

    /**
     * @param OrientatedItem[] $possibleOrientations
     * @param Box              $box
     * @param Item             $item
     * @param OrientatedItem   $prevItem
     * @param Item             $nextItem
     *
     * @return array
     */
    protected function getUsableOrientations(
        $possibleOrientations,
        Box $box,
        Item $item,
        OrientatedItem $prevItem = null,
        Item $nextItem = null
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
        } else if (count($unstableOrientations) > 0) {
            $orientationsInEmptyBox = $this->getPossibleOrientations(
                $item,
                $prevItem,
                $box->getInnerWidth(),
                $box->getInnerLength(),
                $box->getInnerDepth()
            );

            $stableOrientationsInEmptyBox = array_filter(
                $orientationsInEmptyBox,
                function(OrientatedItem $orientation) {
                    return $orientation->isStable();
                }
            );

            if (is_null($nextItem) || count($stableOrientationsInEmptyBox) == 0) {
                $orientationsToUse = $unstableOrientations;
            }
        }

        return $orientationsToUse;
    }
}

