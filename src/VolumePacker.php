<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use function count;
use function max;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Actual packer.
 *
 * @author Doug Wright
 */
class VolumePacker implements LoggerAwareInterface
{
    /**
     * The logger instance.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Box to pack items into.
     *
     * @var Box
     */
    protected $box;

    /**
     * List of items to be packed.
     *
     * @var ItemList
     */
    protected $items;

    /**
     * Whether the packer is in look-ahead mode (i.e. working ahead of the main packing).
     *
     * @var bool
     */
    protected $singlePassMode = false;

    /**
     * @var OrientatedItemFactory
     */
    private $orientatedItemFactory;

    /** @var bool */
    private $hasConstrainedItems;

    /**
     * Constructor.
     */
    public function __construct(Box $box, ItemList $items)
    {
        $this->box = $box;
        $this->items = clone $items;

        $this->logger = new NullLogger();

        $this->hasConstrainedItems = $items->hasConstrainedItems();

        $this->orientatedItemFactory = new OrientatedItemFactory($this->box);
        $this->orientatedItemFactory->setLogger($this->logger);
    }

    /**
     * Sets a logger.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        $this->orientatedItemFactory->setLogger($logger);
    }

    /**
     * @internal
     */
    public function setSinglePassMode(bool $singlePassMode): void
    {
        $this->singlePassMode = $singlePassMode;
    }

    /**
     * Pack as many items as possible into specific given box.
     *
     * @return PackedBox packed box
     */
    public function pack(): PackedBox
    {
        $this->logger->debug("[EVALUATING BOX] {$this->box->getReference()}", ['box' => $this->box]);

        $rotationsToTest = [false];
        if (!$this->singlePassMode) {
            $rotationsToTest[] = true;
        }

        $boxPermutations = [];
        foreach ($rotationsToTest as $rotation) {
            if ($rotation) {
                $boxWidth = $this->box->getInnerLength();
                $boxLength = $this->box->getInnerWidth();
            } else {
                $boxWidth = $this->box->getInnerWidth();
                $boxLength = $this->box->getInnerLength();
            }

            $boxPermutation = $this->packRotation($boxWidth, $boxLength);
            if ($boxPermutation->getItems()->count() === $this->items->count()) {
                return $boxPermutation;
            }

            $boxPermutations[] = $boxPermutation;
        }

        usort($boxPermutations, static function (PackedBox $a, PackedBox $b) {
            return $b->getVolumeUtilisation() <=> $a->getVolumeUtilisation();
        });

        return reset($boxPermutations);
    }

    /**
     * Pack as many items as possible into specific given box.
     *
     * @return PackedBox packed box
     */
    protected function packRotation(int $boxWidth, int $boxLength): PackedBox
    {
        $this->logger->debug("[EVALUATING ROTATION] {$this->box->getReference()}", ['width' => $boxWidth, 'length' => $boxLength]);

        /** @var PackedLayer[] $layers */
        $layers = [];
        $items = clone $this->items;

        while ($items->count() > 0) {
            $layerStartDepth = static::getCurrentPackedDepth($layers);

            //do a preliminary layer pack to get the depth used
            $preliminaryItems = clone $items;
            $preliminaryLayer = $this->packLayer($preliminaryItems, $layers, $layerStartDepth, $boxWidth, $boxLength, $this->box->getInnerDepth() - $layerStartDepth, 0);
            if (count($preliminaryLayer->getItems()) === 0) {
                break;
            }

            if ($preliminaryLayer->getDepth() === $preliminaryLayer->getItems()[0]->getDepth()) { // preliminary === final
                $layers[] = $preliminaryLayer;
                $items = $preliminaryItems;
            } else { //redo with now-known-depth so that we can stack to that height from the first item
                $layers[] = $this->packLayer($items, $layers, $layerStartDepth, $boxWidth, $boxLength, $this->box->getInnerDepth() - $layerStartDepth, $preliminaryLayer->getDepth());
            }
        }

        if ($this->box->getInnerWidth() !== $boxWidth) {
            $layers = static::rotateLayersNinetyDegrees($layers);
        }

        if (!$this->singlePassMode && !$this->hasConstrainedItems) {
            $layers = static::stabiliseLayers($layers);
        }

        return new PackedBox($this->box, $this->getPackedItemList($layers));
    }

    /**
     * Pack items into an individual vertical layer.
     * @internal
     */
    protected function packLayer(ItemList &$items, array $layers, int $z, int $layerWidth, int $lengthLeft, int $depthLeft, int $guidelineLayerDepth): PackedLayer
    {
        $layers[] = $layer = new PackedLayer();
        $prevItem = null;
        $x = $y = $rowLength = 0;
        $skippedItems = [];
        $remainingWeightAllowed = $this->getRemainingWeightAllowed($layers);
        $packedItemList = $this->getPackedItemList($layers);

        while ($items->count() > 0) {
            $itemToPack = $items->extract();

            //skip items that are simply too heavy or too large
            if (!$this->checkNonDimensionalConstraints($itemToPack, $layers, $remainingWeightAllowed)) {
                continue;
            }

            $orientatedItem = $this->orientatedItemFactory->getBestOrientation($itemToPack, $prevItem instanceof PackedItem ? $prevItem->toOrientatedItem() : null, $items, $layerWidth - $x, $lengthLeft, $depthLeft, $rowLength, $x, $y, $z, $packedItemList, $this->singlePassMode);

            if ($orientatedItem instanceof OrientatedItem) {
                $packedItem = PackedItem::fromOrientatedItem($orientatedItem, $x, $y, $z);
                $layer->insert($packedItem);
                $remainingWeightAllowed -= $itemToPack->getWeight();
                $packedItemList->insert($packedItem);

                $rowLength = max($rowLength, $orientatedItem->getLength());

                //Figure out if we can stack the next item vertically on top of this rather than side by side
                //e.g. when we've packed a tall item, and have just put a shorter one next to it.
                $stackableDepth = ($guidelineLayerDepth ?: $layer->getDepth()) - $orientatedItem->getDepth();
                $stackedZ = $z + $orientatedItem->getDepth();
                $stackSkippedItems = [];
                while ($stackableDepth > 0 && $items->count() > 0) {
                    $itemToTryStacking = $items->extract();
                    $stackedItem = $this->orientatedItemFactory->getBestOrientation($itemToTryStacking, $prevItem instanceof PackedItem ? $prevItem->toOrientatedItem() : null, $items, $orientatedItem->getWidth(), $orientatedItem->getLength(), $stackableDepth, $rowLength, $x, $y, $stackedZ, $packedItemList, $this->singlePassMode);
                    if ($stackedItem && $this->checkNonDimensionalConstraints($itemToTryStacking, $layers, $remainingWeightAllowed)) {
                        $layer->insert(PackedItem::fromOrientatedItem($stackedItem, $x, $y, $stackedZ));
                        $remainingWeightAllowed -= $itemToTryStacking->getWeight();
                        $packedItemList->insert($packedItem);
                        $stackableDepth -= $stackedItem->getDepth();
                        $stackedZ += $stackedItem->getDepth();
                    } else {
                        $stackSkippedItems[] = $itemToTryStacking;
                        // abandon here if next item is the same, no point trying to keep going. Last time is not skipped, need that to trigger appropriate reset logic
                        while ($items->count() > 0 && static::isSameDimensions($itemToTryStacking, $items->top())) {
                            $stackSkippedItems[] = $items->extract();
                        }
                    }
                }
                if ($stackSkippedItems) {
                    $items = ItemList::fromArray(array_merge($stackSkippedItems, iterator_to_array($items)), true);
                }
                $x += $orientatedItem->getWidth();

                $prevItem = $packedItem;
                if ($items->count() === 0) {
                    $items = ItemList::fromArray(array_merge($skippedItems, iterator_to_array($items)), true);
                    $skippedItems = [];
                }
            } elseif (count($layer->getItems()) === 0) { // zero items on layer
                $this->logger->debug("doesn't fit on layer even when empty, skipping for good");
                continue;
            } elseif ($items->count() > 0) { // skip for now, move on to the next item
                $this->logger->debug("doesn't fit, skipping for now");
                $skippedItems[] = $itemToPack;
                // abandon here if next item is the same, no point trying to keep going. Last time is not skipped, need that to trigger appropriate reset logic
                while ($items->count() > 2 && static::isSameDimensions($itemToPack, $items->top())) {
                    $skippedItems[] = $items->extract();
                }
            } elseif ($x > 0) {
                $this->logger->debug('No more fit in width wise, resetting for new row');
                $lengthLeft -= $rowLength;
                $y += $rowLength;
                $x = $rowLength = 0;
                $skippedItems[] = $itemToPack;
                $items = ItemList::fromArray(array_merge($skippedItems, iterator_to_array($items)), true);
                $skippedItems = [];
                $prevItem = null;
                continue;
            } else {
                $this->logger->debug('no items fit, so starting next vertical layer');
                $skippedItems[] = $itemToPack;

                $items = ItemList::fromArray(array_merge($skippedItems, iterator_to_array($items)), true);

                return $layer;
            }
        }

        return $layer;
    }

    /**
     * During packing, it is quite possible that layers have been created that aren't physically stable
     * i.e. they overhang the ones below.
     *
     * This function reorders them so that the ones with the greatest surface area are placed at the bottom
     * @param PackedLayer[] $layers
     */
    protected static function stabiliseLayers(array $layers): array
    {
        $stabiliser = new LayerStabiliser();

        return $stabiliser->stabilise($layers);
    }

    /**
     * As well as purely dimensional constraints, there are other constraints that need to be met
     * e.g. weight limits or item-specific restrictions (e.g. max <x> batteries per box).
     */
    protected function checkNonDimensionalConstraints(Item $itemToPack, array $layers, int $remainingWeightAllowed): bool
    {
        $customConstraintsOK = true;
        if ($itemToPack instanceof ConstrainedItem) {
            $customConstraintsOK = $itemToPack->canBePackedInBox($this->getPackedItemList($layers), $this->box);
        }

        return $customConstraintsOK && $itemToPack->getWeight() <= $remainingWeightAllowed;
    }

    /**
     * Swap back width/length of the packed items to match orientation of the box if needed.
     * @param PackedLayer[] $oldLayers
     */
    protected static function rotateLayersNinetyDegrees($oldLayers): array
    {
        $newLayers = [];
        foreach ($oldLayers as $originalLayer) {
            $newLayer = new PackedLayer();
            foreach ($originalLayer->getItems() as $item) {
                $packedItem = new PackedItem($item->getItem(), $item->getY(), $item->getX(), $item->getZ(), $item->getLength(), $item->getWidth(), $item->getDepth());
                $newLayer->insert($packedItem);
            }
            $newLayers[] = $newLayer;
        }

        return $newLayers;
    }

    /**
     * Generate a single list of items packed.
     */
    protected function getPackedItemList(array $layers): PackedItemList
    {
        $packedItemList = new PackedItemList();
        foreach ($layers as $layer) {
            foreach ($layer->getItems() as $packedItem) {
                $packedItemList->insert($packedItem);
            }
        }

        return $packedItemList;
    }

    /**
     * Return the current packed depth.
     * @param PackedLayer[] $layers
     */
    protected static function getCurrentPackedDepth(array $layers): int
    {
        $depth = 0;
        foreach ($layers as $layer) {
            $depth += $layer->getDepth();
        }

        return $depth;
    }

    private function getRemainingWeightAllowed(array $layers): int
    {
        $remainingWeightAllowed = $this->box->getMaxWeight() - $this->box->getEmptyWeight();
        foreach ($layers as $layer) {
            $remainingWeightAllowed -= $layer->getWeight();
        }

        return $remainingWeightAllowed;
    }

    /**
     * Compare two items to see if they have same dimensions.
     */
    protected static function isSameDimensions(Item $itemA, Item $itemB): bool
    {
        if ($itemA === $itemB) {
            return true;
        }
        $itemADimensions = [$itemA->getWidth(), $itemA->getLength(), $itemA->getDepth()];
        $itemBDimensions = [$itemB->getWidth(), $itemB->getLength(), $itemB->getDepth()];
        sort($itemADimensions);
        sort($itemBDimensions);

        return $itemADimensions === $itemBDimensions;
    }
}
