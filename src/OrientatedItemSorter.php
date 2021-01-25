<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use function max;
use function min;
use const PHP_INT_MAX;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Figure out best choice of orientations for an item and a given context.
 *
 * @author Doug Wright
 * @internal
 */
class OrientatedItemSorter implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var int[]
     */
    protected static $lookaheadCache = [];

    /**
     * @var OrientatedItemFactory
     */
    private $orientatedItemFactory;

    /**
     * @var bool
     */
    private $singlePassMode;

    /**
     * @var int
     */
    private $widthLeft;

    /**
     * @var int
     */
    private $lengthLeft;

    /**
     * @var int
     */
    private $depthLeft;

    /**
     * @var int
     */
    private $rowLength;

    /**
     * @var int
     */
    private $x;

    /**
     * @var int
     */
    private $y;

    /**
     * @var int
     */
    private $z;

    /**
     * @var ItemList
     */
    private $nextItems;

    /**
     * @var PackedItemList
     */
    private $prevPackedItemList;

    public function __construct(OrientatedItemFactory $factory, bool $singlePassMode, int $widthLeft, int $lengthLeft, int $depthLeft, ItemList $nextItems, int $rowLength, int $x, int $y, int $z, PackedItemList $prevPackedItemList)
    {
        $this->orientatedItemFactory = $factory;
        $this->singlePassMode = $singlePassMode;
        $this->widthLeft = $widthLeft;
        $this->lengthLeft = $lengthLeft;
        $this->depthLeft = $depthLeft;
        $this->nextItems = $nextItems;
        $this->rowLength = $rowLength;
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
        $this->prevPackedItemList = $prevPackedItemList;
    }

    public function __invoke(OrientatedItem $a, OrientatedItem $b)
    {
        //Prefer exact fits in width/length/depth order
        $orientationAWidthLeft = $this->widthLeft - $a->getWidth();
        $orientationBWidthLeft = $this->widthLeft - $b->getWidth();
        $widthDecider = $this->exactFitDecider($orientationAWidthLeft, $orientationBWidthLeft);
        if ($widthDecider !== 0) {
            return $widthDecider;
        }

        $orientationALengthLeft = $this->lengthLeft - $a->getLength();
        $orientationBLengthLeft = $this->lengthLeft - $b->getLength();
        $lengthDecider = $this->exactFitDecider($orientationALengthLeft, $orientationBLengthLeft);
        if ($lengthDecider !== 0) {
            return $lengthDecider;
        }

        $orientationADepthLeft = $this->depthLeft - $a->getDepth();
        $orientationBDepthLeft = $this->depthLeft - $b->getDepth();
        $depthDecider = $this->exactFitDecider($orientationADepthLeft, $orientationBDepthLeft);
        if ($depthDecider !== 0) {
            return $depthDecider;
        }

        // prefer leaving room for next item(s)
        $followingItemDecider = $this->lookAheadDecider($this->nextItems, $a, $b, $orientationAWidthLeft, $orientationBWidthLeft, $this->widthLeft, $this->lengthLeft, $this->depthLeft, $this->rowLength, $this->x, $this->y, $this->z, $this->prevPackedItemList);
        if ($followingItemDecider !== 0) {
            return $followingItemDecider;
        }

        // otherwise prefer leaving minimum possible gap, or the greatest footprint
        $orientationAMinGap = min($orientationAWidthLeft, $orientationALengthLeft);
        $orientationBMinGap = min($orientationBWidthLeft, $orientationBLengthLeft);

        return $orientationAMinGap <=> $orientationBMinGap ?: $a->getSurfaceFootprint() <=> $b->getSurfaceFootprint();
    }

    private function lookAheadDecider(ItemList $nextItems, OrientatedItem $a, OrientatedItem $b, int $orientationAWidthLeft, int $orientationBWidthLeft, int $widthLeft, int $lengthLeft, int $depthLeft, int $rowLength, int $x, int $y, int $z, PackedItemList $prevPackedItemList): int
    {
        if ($nextItems->count() === 0) {
            return 0;
        }

        $nextItemFitA = $this->orientatedItemFactory->getPossibleOrientations($nextItems->top(), $a, $orientationAWidthLeft, $lengthLeft, $depthLeft, $x, $y, $z, $prevPackedItemList);
        $nextItemFitB = $this->orientatedItemFactory->getPossibleOrientations($nextItems->top(), $b, $orientationBWidthLeft, $lengthLeft, $depthLeft, $x, $y, $z, $prevPackedItemList);
        if ($nextItemFitA && !$nextItemFitB) {
            return -1;
        }
        if ($nextItemFitB && !$nextItemFitA) {
            return 1;
        }

        // if not an easy either/or, do a partial lookahead
        $additionalPackedA = $this->calculateAdditionalItemsPackedWithThisOrientation($a, $nextItems, $widthLeft, $lengthLeft, $depthLeft, $rowLength);
        $additionalPackedB = $this->calculateAdditionalItemsPackedWithThisOrientation($b, $nextItems, $widthLeft, $lengthLeft, $depthLeft, $rowLength);

        return $additionalPackedB <=> $additionalPackedA ?: 0;
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
        if ($this->singlePassMode) {
            return 0;
        }

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
            $tempPacker = new VolumePacker($tempBox, $itemsToPack);
            $tempPacker->setSinglePassMode(true);
            $remainingRowPacked = $tempPacker->pack();

            $itemsToPack->removePackedItems($remainingRowPacked->getItems());

            $tempBox = new WorkingVolume($originalWidthLeft, $originalLengthLeft - $currentRowLength, $depthLeft, PHP_INT_MAX);
            $tempPacker = new VolumePacker($tempBox, $itemsToPack);
            $tempPacker->setSinglePassMode(true);
            $nextRowsPacked = $tempPacker->pack();

            $itemsToPack->removePackedItems($nextRowsPacked->getItems());

            $packedCount = $nextItems->count() - $itemsToPack->count();
            $this->logger->debug('Lookahead with orientation', ['packedCount' => $packedCount, 'orientatedItem' => $prevItem]);

            static::$lookaheadCache[$cacheKey] = $packedCount;
        }

        return static::$lookaheadCache[$cacheKey];
    }

    private function exactFitDecider(int $dimensionALeft, int $dimensionBLeft)
    {
        if ($dimensionALeft === 0 && $dimensionBLeft > 0) {
            return -1;
        }

        if ($dimensionALeft > 0 && $dimensionBLeft === 0) {
            return 1;
        }

        return 0;
    }
}
