<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use Psr\Log\LoggerInterface;

use function max;
use function min;
use function sort;

use const PHP_INT_MAX;

/**
 * Figure out best choice of orientations for an item and a given context.
 * @internal
 */
class OrientatedItemSorter
{
    /**
     * Max (smallest/largest) edge ratio for an item to be treated as slab-like.
     * At or below this, BestFit scoring prefers KeepFlat-like orientations (thin edge vertical).
     */
    private const SLAB_ASPECT_RATIO = 0.3;

    /**
     * @var array<string, int>
     */
    protected static array $lookaheadCache = [];

    public function __construct(
        private readonly OrientatedItemFactory $orientatedItemFactory,
        private readonly bool $singlePassMode,
        private readonly int $widthLeft,
        private readonly int $lengthLeft,
        private readonly int $depthLeft,
        private readonly ItemList $nextItems,
        private readonly int $rowLength,
        private readonly int $x,
        private readonly int $y,
        private readonly int $z,
        private readonly PackedItemList $prevPackedItemList,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(OrientatedItem $a, OrientatedItem $b): int
    {
        // Prefer exact fits in width/length/depth order
        $orientationAWidthLeft = $this->widthLeft - $a->width;
        $orientationBWidthLeft = $this->widthLeft - $b->width;
        $widthDecider = $this->exactFitDecider($orientationAWidthLeft, $orientationBWidthLeft);
        if ($widthDecider !== 0) {
            return $widthDecider;
        }

        $orientationALengthLeft = $this->lengthLeft - $a->length;
        $orientationBLengthLeft = $this->lengthLeft - $b->length;
        $lengthDecider = $this->exactFitDecider($orientationALengthLeft, $orientationBLengthLeft);
        if ($lengthDecider !== 0) {
            return $lengthDecider;
        }

        $orientationADepthLeft = $this->depthLeft - $a->depth;
        $orientationBDepthLeft = $this->depthLeft - $b->depth;
        $depthDecider = $this->exactFitDecider($orientationADepthLeft, $orientationBDepthLeft);
        if ($depthDecider !== 0) {
            return $depthDecider;
        }

        // prefer leaving room for next item(s)
        $followingItemDecider = $this->lookAheadDecider($a, $b, $orientationAWidthLeft, $orientationBWidthLeft);
        if ($followingItemDecider !== 0) {
            return $followingItemDecider;
        }

        // For slab-like BestFit items, prefer thin edge as depth (KeepFlat-style)
        if (
            $a->item->getAllowedRotation() === Rotation::BestFit
            && self::isSlabLike($a->item)
        ) {
            $thin = min($a->item->getWidth(), $a->item->getLength(), $a->item->getDepth());
            $slabDecider = ($b->depth === $thin) <=> ($a->depth === $thin);
            if ($slabDecider !== 0) {
                return $slabDecider;
            }
        }

        // otherwise prefer leaving minimum possible gap, or the greatest footprint
        $orientationAMinGap = min($orientationAWidthLeft, $orientationALengthLeft);
        $orientationBMinGap = min($orientationBWidthLeft, $orientationBLengthLeft);

        return $orientationAMinGap <=> $orientationBMinGap ?: $a->surfaceFootprint <=> $b->surfaceFootprint;
    }

    /**
     * Whether the item has one edge much shorter than the longest.
     */
    private static function isSlabLike(Item $item): bool
    {
        $dims = [$item->getWidth(), $item->getLength(), $item->getDepth()];
        sort($dims);
        if ($dims[2] === 0) {
            return false; // avoid divide by zero
        }

        return ($dims[0] / $dims[2]) <= self::SLAB_ASPECT_RATIO;
    }

    private function lookAheadDecider(OrientatedItem $a, OrientatedItem $b, int $orientationAWidthLeft, int $orientationBWidthLeft): int
    {
        if ($this->nextItems->count() === 0) {
            return 0;
        }

        $nextItemFitA = $this->orientatedItemFactory->getPossibleOrientations($this->nextItems->top(), $a, $orientationAWidthLeft, $this->lengthLeft, $this->depthLeft, $this->x, $this->y, $this->z, $this->prevPackedItemList);
        $nextItemFitB = $this->orientatedItemFactory->getPossibleOrientations($this->nextItems->top(), $b, $orientationBWidthLeft, $this->lengthLeft, $this->depthLeft, $this->x, $this->y, $this->z, $this->prevPackedItemList);
        if ($nextItemFitA && !$nextItemFitB) {
            return -1;
        }
        if ($nextItemFitB && !$nextItemFitA) {
            return 1;
        }

        // if not an easy either/or, do a partial lookahead
        $additionalPackedA = $this->calculateAdditionalItemsPackedWithThisOrientation($a);
        $additionalPackedB = $this->calculateAdditionalItemsPackedWithThisOrientation($b);

        return $additionalPackedB <=> $additionalPackedA ?: 0;
    }

    /**
     * Approximation of a forward-looking packing.
     *
     * Not an actual packing, that has additional logic regarding constraints and stackability, this focuses
     * purely on fit.
     */
    protected function calculateAdditionalItemsPackedWithThisOrientation(
        OrientatedItem $prevItem
    ): int {
        if ($this->singlePassMode) {
            return 0;
        }

        $currentRowLength = max($prevItem->length, $this->rowLength);

        $itemsToPack = $this->nextItems->topN(7); // cap lookahead as this gets recursive and slow

        $cacheKey = $this->widthLeft .
            '|' .
            $this->lengthLeft .
            '|' .
            $prevItem->width .
            '|' .
            $prevItem->length .
            '|' .
            $currentRowLength .
            '|'
            . $this->depthLeft;

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
                $itemToPack->getAllowedRotation()->name;
        }

        if (!isset(static::$lookaheadCache[$cacheKey])) {
            $tempBox = new WorkingVolume($this->widthLeft - $prevItem->width, $currentRowLength, $this->depthLeft, PHP_INT_MAX);
            $tempPacker = new VolumePacker($tempBox, $itemsToPack);
            $tempPacker->setSinglePassMode(true);
            $remainingRowPacked = $tempPacker->pack();

            $itemsToPack->removePackedItems($remainingRowPacked->items);

            $tempBox = new WorkingVolume($this->widthLeft, $this->lengthLeft - $currentRowLength, $this->depthLeft, PHP_INT_MAX);
            $tempPacker = new VolumePacker($tempBox, $itemsToPack);
            $tempPacker->setSinglePassMode(true);
            $nextRowsPacked = $tempPacker->pack();

            $itemsToPack->removePackedItems($nextRowsPacked->items);

            $packedCount = $this->nextItems->count() - $itemsToPack->count();
            $this->logger->debug('Lookahead with orientation', ['packedCount' => $packedCount, 'orientatedItem' => $prevItem]);

            static::$lookaheadCache[$cacheKey] = $packedCount;
        }

        return static::$lookaheadCache[$cacheKey];
    }

    private function exactFitDecider(int $dimensionALeft, int $dimensionBLeft): int
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
