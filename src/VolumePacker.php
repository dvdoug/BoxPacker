<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function count;
use function reset;
use function sort;
use function usort;

use const PHP_INT_MAX;

/**
 * Actual packer.
 */
class VolumePacker implements LoggerAwareInterface
{
    protected LoggerInterface $logger;

    protected ItemList $items;

    protected bool $singlePassMode = false;

    protected bool $packAcrossWidthOnly = false;

    private readonly LayerPacker $layerPacker;

    protected bool $beStrictAboutItemOrdering = false;

    private readonly bool $hasConstrainedItems;

    private readonly bool $hasNoRotationItems;

    public function __construct(protected Box $box, ItemList $items)
    {
        $this->items = clone $items;

        $this->logger = new NullLogger();

        $this->hasConstrainedItems = $items->hasConstrainedItems();
        $this->hasNoRotationItems = $items->hasNoRotationItems();

        $this->layerPacker = new LayerPacker($this->box);
        $this->layerPacker->setLogger($this->logger);
    }

    /**
     * Sets a logger.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        $this->layerPacker->setLogger($logger);
    }

    public function packAcrossWidthOnly(): void
    {
        $this->packAcrossWidthOnly = true;
    }

    public function beStrictAboutItemOrdering(bool $beStrict): void
    {
        $this->beStrictAboutItemOrdering = $beStrict;
        $this->layerPacker->beStrictAboutItemOrdering($beStrict);
    }

    /**
     * @internal
     */
    public function setSinglePassMode(bool $singlePassMode): void
    {
        $this->singlePassMode = $singlePassMode;
        if ($singlePassMode) {
            $this->packAcrossWidthOnly = true;
        }
        $this->layerPacker->setSinglePassMode($singlePassMode);
    }

    /**
     * Pack as many items as possible into specific given box.
     *
     * @return PackedBox packed box
     */
    public function pack(): PackedBox
    {
        $orientatedItemFactory = new OrientatedItemFactory($this->box);
        $orientatedItemFactory->setLogger($this->logger);
        $this->logger->debug("[EVALUATING BOX] {$this->box->getReference()}", ['box' => $this->box]);

        // Sometimes "space available" decisions depend on orientation of the box, so try both ways
        $rotationsToTest = [false];
        if (!$this->packAcrossWidthOnly && !$this->hasNoRotationItems) {
            $rotationsToTest[] = true;
        }

        // The orientation of the first item can have an outsized effect on the rest of the placement, so special-case
        // that and try everything

        $boxPermutations = [];
        foreach ($rotationsToTest as $rotation) {
            if ($rotation) {
                $boxWidth = $this->box->getInnerLength();
                $boxLength = $this->box->getInnerWidth();
            } else {
                $boxWidth = $this->box->getInnerWidth();
                $boxLength = $this->box->getInnerLength();
            }

            $specialFirstItemOrientations = [null];
            if (!$this->singlePassMode) {
                $specialFirstItemOrientations = $orientatedItemFactory->getPossibleOrientations($this->items->top(), null, $boxWidth, $boxLength, $this->box->getInnerDepth(), 0, 0, 0, new PackedItemList()) ?: [null];
            }

            foreach ($specialFirstItemOrientations as $firstItemOrientation) {
                $boxPermutation = $this->packRotation($boxWidth, $boxLength, $firstItemOrientation);
                if ($boxPermutation->items->count() === $this->items->count()) {
                    return $boxPermutation;
                }

                $boxPermutations[] = $boxPermutation;
            }
        }

        usort($boxPermutations, static fn (PackedBox $a, PackedBox $b) => $b->getVolumeUtilisation() <=> $a->getVolumeUtilisation());

        return reset($boxPermutations);
    }

    /**
     * Pack as many items as possible into specific given box.
     *
     * @return PackedBox packed box
     */
    private function packRotation(int $boxWidth, int $boxLength, ?OrientatedItem $firstItemOrientation): PackedBox
    {
        $this->logger->debug("[EVALUATING ROTATION] {$this->box->getReference()}", ['width' => $boxWidth, 'length' => $boxLength]);
        $this->layerPacker->setBoxIsRotated($this->box->getInnerWidth() !== $boxWidth);

        $layers = [];
        $items = clone $this->items;

        while ($items->count() > 0) {
            $layerStartDepth = self::getCurrentPackedDepth($layers);
            $packedItemList = $this->getPackedItemList($layers);

            if ($packedItemList->count() > 0) {
                $firstItemOrientation = null;
            }

            // do a preliminary layer pack to get the depth used
            $preliminaryItems = clone $items;
            $preliminaryLayer = $this->layerPacker->packLayer($preliminaryItems, clone $packedItemList, 0, 0, $layerStartDepth, $boxWidth, $boxLength, $this->box->getInnerDepth() - $layerStartDepth, 0, true, $firstItemOrientation);
            if ($preliminaryLayer->items === []) {
                break;
            }

            $preliminaryLayerDepth = $preliminaryLayer->depth;
            if ($preliminaryLayerDepth === $preliminaryLayer->items[0]->depth) { // preliminary === final
                $layers[] = $preliminaryLayer;
                $items = $preliminaryItems;
            } else { // redo with now-known-depth so that we can stack to that height from the first item
                $layers[] = $this->layerPacker->packLayer($items, $packedItemList, 0, 0, $layerStartDepth, $boxWidth, $boxLength, $this->box->getInnerDepth() - $layerStartDepth, $preliminaryLayerDepth, true, $firstItemOrientation);
            }
        }

        if (!$this->singlePassMode && $layers) {
            $layers = $this->stabiliseLayers($layers);
            $layers = $this->fillVoids($layers, $items, $boxWidth, $boxLength);
        }

        $layers = $this->correctLayerRotation($layers, $boxWidth);

        return new PackedBox($this->box, $this->getPackedItemList($layers));
    }

    /**
     * @param  PackedLayer[] $layers
     * @return PackedLayer[]
     */
    private function fillVoids(array $layers, ItemList &$items, int $boxWidth, int $boxLength): array
    {
        $voidFinder = new VoidFinder();
        $packedItemList = $this->getPackedItemList($layers);
        $voids = $voidFinder->find($boxWidth, $boxLength, $this->box->getInnerDepth(), $packedItemList);

        while ($items->count() > 0 && $voids !== []) {
            // Prefer lower positions, then larger free regions (volume, then footprint)
            usort(
                $voids,
                static fn (VoidSpace $a, VoidSpace $b): int => $a->z <=> $b->z
                        ?: $b->volume <=> $a->volume
                        ?: $b->footprint <=> $a->footprint
            );

            $minRemainingVolume = $this->minRemainingItemVolume($items);

            $progress = false;
            foreach ($voids as $void) {
                // Pure performance: skip packLayer when no remaining item can geometrically fit
                if ($void->volume < $minRemainingVolume || !$this->voidMayFitAnyRemainingItem($void, $items)) {
                    continue;
                }

                $packedBefore = $packedItemList->count();
                $layer = $this->layerPacker->packLayer(
                    $items,
                    $packedItemList,
                    $void->x,
                    $void->y,
                    $void->z,
                    $void->x + $void->width,
                    $void->y + $void->length,
                    $void->depth,
                    $void->depth,
                    false, // gap fill; stability assumed from surrounding geometry
                    null
                );

                if ($layer->items !== []) {
                    $layers[] = $layer;
                    $voids = $voidFinder->subtractItems($voids, $layer->items);
                    $progress = true;
                    $this->logger->debug(
                        'Filled void space',
                        [
                            'void' => $void,
                            'itemsPlaced' => $packedItemList->count() - $packedBefore,
                        ]
                    );
                    break;
                }
            }

            if (!$progress) {
                break;
            }
        }

        return $layers;
    }

    private function minRemainingItemVolume(ItemList $items): int
    {
        $minVolume = PHP_INT_MAX;
        foreach ($items as $item) {
            $volume = $item->getWidth() * $item->getLength() * $item->getDepth();
            if ($volume < $minVolume) {
                $minVolume = $volume;
            }
        }

        return $minVolume === PHP_INT_MAX ? 0 : $minVolume;
    }

    /**
     * Cheap geometric reject: can any remaining item fit in this void under its rotation rules?
     */
    private function voidMayFitAnyRemainingItem(VoidSpace $void, ItemList $items): bool
    {
        foreach ($items as $item) {
            if ($this->itemMayFitInVoid($item, $void)) {
                return true;
            }
        }

        return false;
    }

    private function itemMayFitInVoid(Item $item, VoidSpace $void): bool
    {
        $w = $item->getWidth();
        $l = $item->getLength();
        $d = $item->getDepth();

        return match ($item->getAllowedRotation()) {
            Rotation::Never => $w <= $void->width && $l <= $void->length && $d <= $void->depth,
            Rotation::KeepFlat => ($w <= $void->width && $l <= $void->length && $d <= $void->depth)
                || ($l <= $void->width && $w <= $void->length && $d <= $void->depth),
            Rotation::BestFit => $this->bestFitMayFitInVoid($w, $l, $d, $void),
        };
    }

    private function bestFitMayFitInVoid(int $w, int $l, int $d, VoidSpace $void): bool
    {
        $itemEdges = [$w, $l, $d];
        $voidEdges = [$void->width, $void->length, $void->depth];
        sort($itemEdges);
        sort($voidEdges);

        return $itemEdges[0] <= $voidEdges[0]
            && $itemEdges[1] <= $voidEdges[1]
            && $itemEdges[2] <= $voidEdges[2];
    }

    /**
     * During packing, it is quite possible that layers have been created that aren't physically stable
     * i.e. they overhang the ones below.
     *
     * This function reorders them so that the ones with the greatest surface area are placed at the bottom
     *
     * @param  PackedLayer[] $oldLayers
     * @return PackedLayer[]
     */
    private function stabiliseLayers(array $oldLayers): array
    {
        if ($this->hasConstrainedItems || $this->beStrictAboutItemOrdering) { // constraints include position, so cannot change
            return $oldLayers;
        }

        $stabiliser = new LayerStabiliser();

        return $stabiliser->stabilise($oldLayers);
    }

    /**
     * Swap back width/length of the packed items to match orientation of the box if needed.
     *
     * @param PackedLayer[] $oldLayers
     *
     * @return PackedLayer[]
     */
    private function correctLayerRotation(array $oldLayers, int $boxWidth): array
    {
        if ($this->box->getInnerWidth() === $boxWidth) {
            return $oldLayers;
        }

        $newLayers = [];
        foreach ($oldLayers as $originalLayer) {
            $newLayer = new PackedLayer();
            foreach ($originalLayer->items as $item) {
                $packedItem = new PackedItem($item->item, $item->y, $item->x, $item->z, $item->length, $item->width, $item->depth);
                $newLayer->insert($packedItem);
            }
            $newLayers[] = $newLayer;
        }

        return $newLayers;
    }

    /**
     * Generate a single list of items packed.
     * @param PackedLayer[] $layers
     */
    private function getPackedItemList(array $layers): PackedItemList
    {
        $packedItemList = new PackedItemList();
        foreach ($layers as $layer) {
            foreach ($layer->items as $packedItem) {
                $packedItemList->insert($packedItem);
            }
        }

        return $packedItemList;
    }

    /**
     * Return the current packed depth.
     *
     * @param PackedLayer[] $layers
     */
    private static function getCurrentPackedDepth(array $layers): int
    {
        $depth = 0;
        foreach ($layers as $layer) {
            $depth += $layer->depth;
        }

        return $depth;
    }
}
