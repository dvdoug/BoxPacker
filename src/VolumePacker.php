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
use function min;
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
     * @var int
     */
    protected $boxWidth;

    /**
     * @var int
     */
    protected $boxLength;

    /**
     * List of items to be packed.
     *
     * @var ItemList
     */
    protected $items;

    /**
     * Whether the box was rotated for packing.
     *
     * @var bool
     */
    protected $boxRotated = false;

    /**
     * @var PackedLayer[]
     */
    protected $layers = [];

    /**
     * Whether the packer is in look-ahead mode (i.e. working ahead of the main packing).
     *
     * @var bool
     */
    protected $lookAheadMode = false;

    /**
     * @var OrientatedItemFactory
     */
    private $orientatedItemFactory;

    /**
     * @var OrientatedItem[]
     */
    private $orientatedItemCache = [];

    /** @var bool */
    private $hasConstrainedItems;

    /**
     * Constructor.
     */
    public function __construct(Box $box, ItemList $items)
    {
        $this->box = $box;
        $this->items = clone $items;

        $this->boxWidth = max($this->box->getInnerWidth(), $this->box->getInnerLength());
        $this->boxLength = min($this->box->getInnerWidth(), $this->box->getInnerLength());
        $this->logger = new NullLogger();

        // we may have just rotated the box for packing purposes, record if we did
        if ($this->box->getInnerWidth() !== $this->boxWidth || $this->box->getInnerLength() !== $this->boxLength) {
            $this->boxRotated = true;
        }

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
    public function setLookAheadMode(bool $lookAhead): void
    {
        $this->lookAheadMode = $lookAhead;
    }

    /**
     * Pack as many items as possible into specific given box.
     *
     * @return PackedBox packed box
     */
    public function pack(): PackedBox
    {
        $this->logger->debug("[EVALUATING BOX] {$this->box->getReference()}", ['box' => $this->box]);

        while ($this->items->count() > 0) {
            $layerStartDepth = static::getCurrentPackedDepth($this->layers);

            //do a preliminary layer to get the depth used
            $preliminaryLayers = $this->layers;
            $preliminaryItems = $this->packLayer(clone $this->items, $preliminaryLayers, $layerStartDepth, $this->boxWidth, $this->boxLength, $this->box->getInnerDepth() - $layerStartDepth, 0);

            if ($preliminaryItems->count() === 0) { // preliminary === final
                $this->layers = $preliminaryLayers;
                $this->items = $preliminaryItems;
            } else {
                $this->items = $this->packLayer($this->items, $this->layers, $layerStartDepth, $this->boxWidth, $this->boxLength, $this->box->getInnerDepth() - $layerStartDepth, end($preliminaryLayers)->getDepth());
            }
        }

        if ($this->boxRotated) {
            $this->rotateLayersNinetyDegrees();
        }

        if (!$this->lookAheadMode && !$this->hasConstrainedItems) {
            $this->layers = static::stabiliseLayers($this->layers);
        }

        $this->logger->debug('done with this box ' . $this->box->getReference());

        return new PackedBox($this->box, $this->getPackedItemList($this->layers));
    }

    /**
     * Pack items into an individual vertical layer.
     */
    protected function packLayer(ItemList $items, array &$layers, int $z, int $layerWidth, int $lengthLeft, int $depthLeft, int $guidelineLayerDepth): ItemList
    {
        $layers[] = $layer = new PackedLayer();
        $prevItem = null;
        $x = $y = $rowLength = 0;
        $skippedItems = [];

        while ($items->count() > 0) {
            $itemToPack = $items->extract();

            //skip items that are simply too heavy or too large
            if (!$this->checkNonDimensionalConstraints($itemToPack, $layers)) {
                continue;
            }

            $orientatedItem = $this->getOrientationForItem($itemToPack, $prevItem, $items, $layers, count($skippedItems) + $items->count() === 0, $layerWidth - $x, $lengthLeft, $depthLeft, $rowLength, $x, $y, $z);

            if ($orientatedItem instanceof OrientatedItem) {
                $packedItem = PackedItem::fromOrientatedItem($orientatedItem, $x, $y, $z);
                $layer->insert($packedItem);

                $rowLength = max($rowLength, $orientatedItem->getLength());

                //Figure out if we can stack the next item vertically on top of this rather than side by side
                //e.g. when we've packed a tall item, and have just put a shorter one next to it.
                $stackableDepth = ($guidelineLayerDepth ?: $layer->getDepth()) - $orientatedItem->getDepth();
                $stackedZ = $z + $orientatedItem->getDepth();
                $stackSkippedItems = [];
                while ($items->count() > 0) {
                    $itemToTryStacking = $items->extract();
                    $stackedItem = $this->getOrientationForItem($itemToTryStacking, $prevItem, $items, $layers, $items->count() === 1, $orientatedItem->getWidth(), $orientatedItem->getLength(), $stackableDepth, $rowLength, $x, $y, $stackedZ);
                    if ($stackedItem && $this->checkNonDimensionalConstraints($itemToTryStacking, $layers)) {
                        $layer->insert(PackedItem::fromOrientatedItem($stackedItem, $x, $y, $stackedZ));
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

                return ItemList::fromArray(array_merge($skippedItems, iterator_to_array($items)), true);
            }
        }

        return $items;
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

    protected function getOrientationForItem(
        Item $itemToPack,
        ?PackedItem $prevItem,
        ItemList $nextItems,
        array $layers,
        bool $isLastItem,
        int $maxWidth,
        int $maxLength,
        int $maxDepth,
        int $rowLength,
        int $x,
        int $y,
        int $z
    ): ?OrientatedItem {
        $this->logger->debug(
            "evaluating item {$itemToPack->getDescription()} for fit",
            [
                'item' => $itemToPack,
                'space' => [
                    'maxWidth' => $maxWidth,
                    'maxLength' => $maxLength,
                    'maxDepth' => $maxDepth,
                ],
            ]
        );

        $cacheKey = spl_object_hash($itemToPack) . '|' . $x . '|' . $y . '|' . $z . '|' . $nextItems->count();

        if (!isset($this->orientatedItemCache[$cacheKey])) {
            $prevOrientatedItem = $prevItem ? $prevItem->toOrientatedItem() : null;
            $prevPackedItemList = $itemToPack instanceof ConstrainedPlacementItem ? $this->getPackedItemList($layers) : new PackedItemList(); // don't calculate it if not going to be used

            $this->orientatedItemCache[$cacheKey] = $this->orientatedItemFactory->getBestOrientation($itemToPack, $prevOrientatedItem, $nextItems, $isLastItem, $maxWidth, $maxLength, $maxDepth, $rowLength, $x, $y, $z, $prevPackedItemList);
        }

        return $this->orientatedItemCache[$cacheKey];
    }

    /**
     * As well as purely dimensional constraints, there are other constraints that need to be met
     * e.g. weight limits or item-specific restrictions (e.g. max <x> batteries per box).
     */
    protected function checkNonDimensionalConstraints(Item $itemToPack, array $layers): bool
    {
        $customConstraintsOK = true;
        if ($itemToPack instanceof ConstrainedItem) {
            $customConstraintsOK = $itemToPack->canBePackedInBox($this->getPackedItemList($layers), $this->box);
        }

        return $customConstraintsOK && $itemToPack->getWeight() <= $this->getRemainingWeightAllowed($layers);
    }

    /**
     * Swap back width/length of the packed items to match orientation of the box if needed.
     */
    protected function rotateLayersNinetyDegrees(): void
    {
        foreach ($this->layers as $i => $originalLayer) {
            $newLayer = new PackedLayer();
            foreach ($originalLayer->getItems() as $item) {
                $packedItem = new PackedItem($item->getItem(), $item->getY(), $item->getX(), $item->getZ(), $item->getLength(), $item->getWidth(), $item->getDepth());
                $newLayer->insert($packedItem);
            }
            $this->layers[$i] = $newLayer;
        }
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
