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
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
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
     * Whether the packer is in single-pass mode.
     *
     * @var bool
     */
    protected $singlePassMode = false;

    /**
     * @var array<string, array<OrientatedItem>>
     */
    protected static $emptyBoxCache = [];

    public function __construct(Box $box)
    {
        $this->box = $box;
        $this->logger = new NullLogger();
    }

    public function setSinglePassMode(bool $singlePassMode): void
    {
        $this->singlePassMode = $singlePassMode;
    }

    /**
     * Get the best orientation for an item.
     */
    public function getBestOrientation(
        Item $item,
        ?OrientatedItem $prevItem,
        ItemList $nextItems,
        int $widthLeft,
        int $lengthLeft,
        int $depthLeft,
        int $rowLength,
        int $x,
        int $y,
        int $z,
        PackedItemList $prevPackedItemList,
        bool $considerStability
    ): ?OrientatedItem {
        $this->logger->debug(
            "evaluating item {$item->getDescription()} for fit",
            [
                'item' => $item,
                'space' => [
                    'widthLeft' => $widthLeft,
                    'lengthLeft' => $lengthLeft,
                    'depthLeft' => $depthLeft,
                ],
            ]
        );

        $possibleOrientations = $this->getPossibleOrientations($item, $prevItem, $widthLeft, $lengthLeft, $depthLeft, $x, $y, $z, $prevPackedItemList);
        $usableOrientations = $considerStability ? $this->getUsableOrientations($item, $possibleOrientations) : $possibleOrientations;

        if (empty($usableOrientations)) {
            return null;
        }

        $sorter = new OrientatedItemSorter($this, $this->singlePassMode, $widthLeft, $lengthLeft, $depthLeft, $nextItems, $rowLength, $x, $y, $z, $prevPackedItemList, $this->logger);
        usort($usableOrientations, $sorter);

        $this->logger->debug('Selected best fit orientation', ['orientation' => $usableOrientations[0]]);

        return $usableOrientations[0];
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
        $permutations = $this->generatePermutations($item, $prevItem);

        //remove any that simply don't fit
        $orientations = [];
        foreach ($permutations as $dimensions) {
            if ($dimensions[0] <= $widthLeft && $dimensions[1] <= $lengthLeft && $dimensions[2] <= $depthLeft) {
                $orientations[] = new OrientatedItem($item, $dimensions[0], $dimensions[1], $dimensions[2]);
            }
        }

        if ($item instanceof ConstrainedPlacementItem && !$this->box instanceof WorkingVolume) {
            $orientations = array_filter($orientations, function (OrientatedItem $i) use ($x, $y, $z, $prevPackedItemList) {
                /** @var ConstrainedPlacementItem */
                $constrainedItem = $i->getItem();

                return $constrainedItem->canBePacked(new PackedBox($this->box, $prevPackedItemList), $x, $y, $z, $i->getWidth(), $i->getLength(), $i->getDepth());
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
            $item->getAllowedRotations() .
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
     * @param  OrientatedItem[] $possibleOrientations
     * @return OrientatedItem[]
     */
    protected function getUsableOrientations(
        Item $item,
        array $possibleOrientations
    ): array {
        $stableOrientations = $unstableOrientations = [];

        // Divide possible orientations into stable (low centre of gravity) and unstable (high centre of gravity)
        foreach ($possibleOrientations as $orientation) {
            if ($orientation->isStable() || $this->box->getInnerDepth() === $orientation->getDepth()) {
                $stableOrientations[] = $orientation;
            } else {
                $unstableOrientations[] = $orientation;
            }
        }

        /*
         * We prefer to use stable orientations only, but allow unstable ones if
         * the item doesn't fit in the box any other way
         */
        if (count($stableOrientations) > 0) {
            return $stableOrientations;
        }

        if (count($unstableOrientations) > 0) {
            $stableOrientationsInEmptyBox = $this->getStableOrientationsInEmptyBox($item);

            if (count($stableOrientationsInEmptyBox) === 0) {
                return $unstableOrientations;
            }
        }

        return [];
    }

    /**
     * Return the orientations for this item if it were to be placed into the box with nothing else.
     * @return OrientatedItem[]
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
     * @return array<array<int>>
     */
    private function generatePermutations(Item $item, ?OrientatedItem $prevItem): array
    {
        //Special case items that are the same as what we just packed - keep orientation
        if ($prevItem && $prevItem->isSameDimensions($item)) {
            return [[$prevItem->getWidth(), $prevItem->getLength(), $prevItem->getDepth()]];
        }

        $permutations = [];
        $w = $item->getWidth();
        $l = $item->getLength();
        $d = $item->getDepth();

        $permutations[$w . $l . $d] = [$w, $l, $d];

        if ($item->getAllowedRotations() > 1) { //simple 2D rotation
            $permutations[$l . $w . $d] = [$l, $w, $d];
        }

        if ($item->getAllowedRotations() === Item::ROTATION_BEST_FIT) { //add 3D rotation if we're allowed
            $permutations[$w . $d . $l] = [$w, $d, $l];
            $permutations[$l . $d . $w] = [$l, $d, $w];
            $permutations[$d . $w . $l] = [$d, $w, $l];
            $permutations[$d . $l . $w] = [$d, $l, $w];
        }

        return $permutations;
    }

    /**
     * Clear the empty box cache. Useful is part of a long running 
     * process to prevent memory leaks.
     */
    public static function clearEmptyBoxCache() : void
    {
        self::$emptyBoxCache = [];
    }
}
