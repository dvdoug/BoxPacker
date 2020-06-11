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
     * Whether the packer is in single-pass mode.
     *
     * @var bool
     */
    protected $singlePassMode = false;

    /**
     * @var OrientatedItem[]
     */
    protected static $emptyBoxCache = [];

    public function __construct(Box $box)
    {
        $this->box = $box;
        $this->logger = new NullLogger();
    }

    public function setSinglePassMode($singlePassMode)
    {
        $this->singlePassMode = $singlePassMode;
    }

    /**
     * Get the best orientation for an item.
     *
     * @param Item                $item
     * @param OrientatedItem|null $prevItem
     * @param ItemList            $nextItems
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
        $widthLeft,
        $lengthLeft,
        $depthLeft,
        $rowLength,
        $x,
        $y,
        $z,
        PackedItemList $prevPackedItemList
    ) {
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
        $usableOrientations = $this->getUsableOrientations($item, $possibleOrientations);

        if (empty($usableOrientations)) {
            return null;
        }

        $sorter = new OrientatedItemSorter($this, $this->singlePassMode, $widthLeft, $lengthLeft, $depthLeft, $nextItems, $rowLength, $x, $y, $z, $prevPackedItemList);
        $sorter->setLogger($this->logger);
        usort($usableOrientations, $sorter);

        $this->logger->debug('Selected best fit orientation', ['orientation' => $usableOrientations[0]]);

        return $usableOrientations[0];
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
                return $i->getItem()->canBePacked($this->box, clone $prevPackedItemList, $x, $y, $z, $i->getWidth(), $i->getLength(), $i->getDepth());
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
     * @param Item             $item
     * @param OrientatedItem[] $possibleOrientations
     * @param bool             $isLastItem
     *
     * @return OrientatedItem[]
     */
    protected function getUsableOrientations(
        Item $item,
        array $possibleOrientations
    ) {
        $orientationsToUse = $stableOrientations = $unstableOrientations = [];

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
            $orientationsToUse = $stableOrientations;
        } elseif (count($unstableOrientations) > 0) {
            $stableOrientationsInEmptyBox = $this->getStableOrientationsInEmptyBox($item);

            if (count($stableOrientationsInEmptyBox) === 0) {
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

    private function generatePermutations(Item $item, OrientatedItem $prevItem = null)
    {
        $permutations = [];

        //Special case items that are the same as what we just packed - keep orientation
        if ($prevItem && $prevItem->isSameDimensions($item)) {
            $permutations[] = [$prevItem->getWidth(), $prevItem->getLength(), $prevItem->getDepth()];
        } else {
            //simple 2D rotation
            $permutations[] = [$item->getWidth(), $item->getLength(), $item->getDepth()];
            $permutations[] = [$item->getLength(), $item->getWidth(), $item->getDepth()];

            //add 3D rotation if we're allowed
            if (!$item->getKeepFlat()) {
                $permutations[] = [$item->getWidth(), $item->getDepth(), $item->getLength()];
                $permutations[] = [$item->getLength(), $item->getDepth(), $item->getWidth()];
                $permutations[] = [$item->getDepth(), $item->getWidth(), $item->getLength()];
                $permutations[] = [$item->getDepth(), $item->getLength(), $item->getWidth()];
            }
        }

        return $permutations;
    }
}
