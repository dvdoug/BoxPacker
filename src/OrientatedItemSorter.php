<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use Psr\Log\LoggerInterface;

use function intdiv;
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
     * Only special-case orientation when at least this many same-size items are still to place
     * (including the current one). Requires the whole remaining list to be that size.
     */
    private const MIN_SAME_SIZE_ITEMS = 2;

    /**
     * @var array<string, int>
     */
    protected static array $lookaheadCache = [];

    /**
     * Whether every remaining item matches the current item's size.
     * Fixed for the life of this sorter (one orientation sort); computed once.
     */
    private ?bool $allRemainingSameSizeCache = null;

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

        // Many same-size items left: prefer the orientation that fits more of them in this free space
        // simple arithmetic, not a recursive packer pass.
        $sameSizeDecider = $this->sameSizeItemsDecider($a, $b);
        if ($sameSizeDecider !== 0) {
            return $sameSizeDecider;
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
     * When every remaining item is the same size, prefer the orientation that can fit more of them
     * into the free width × length × depth on a regular grid.
     */
    private function sameSizeItemsDecider(OrientatedItem $a, OrientatedItem $b): int
    {
        if (!$this->allRemainingSameSize($a)) {
            return 0;
        }

        $remaining = $this->nextItems->count() + 1;
        if ($remaining < self::MIN_SAME_SIZE_ITEMS) {
            return 0;
        }

        $fitA = $this->howManyFitWithOrientation($a->width, $a->length, $a->depth);
        $fitB = $this->howManyFitWithOrientation($b->width, $b->length, $b->depth);

        $coversAllA = $fitA >= $remaining;
        $coversAllB = $fitB >= $remaining;
        if ($coversAllA !== $coversAllB) {
            return ($coversAllB ? 1 : 0) <=> ($coversAllA ? 1 : 0);
        }

        return $fitB <=> $fitA;
    }

    /**
     * How many of this orientation fit in the free space if lined up on a regular grid.
     */
    private function howManyFitWithOrientation(int $width, int $length, int $depth): int
    {
        if ($width <= 0 || $length <= 0 || $depth <= 0) {
            return 0;
        }

        return intdiv($this->widthLeft, $width)
            * intdiv($this->lengthLeft, $length)
            * intdiv($this->depthLeft, $depth);
    }

    private function allRemainingSameSize(OrientatedItem $oriented): bool
    {
        if ($this->allRemainingSameSizeCache !== null) {
            return $this->allRemainingSameSizeCache;
        }

        if ($this->nextItems->count() === 0) {
            $this->allRemainingSameSizeCache = true;

            return true;
        }

        foreach ($this->nextItems as $next) {
            if (!$oriented->isSameDimensions($next)) {
                $this->allRemainingSameSizeCache = false;

                return false;
            }
        }

        $this->allRemainingSameSizeCache = true;

        return true;
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
