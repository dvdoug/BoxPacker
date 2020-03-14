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
            $layerStartDepth = $this->getCurrentPackedDepth();
            $this->items = $this->packLayer($this->items, $layerStartDepth, $this->boxWidth, $this->boxLength, $this->box->getInnerDepth() - $layerStartDepth);
        }

        if ($this->boxRotated) {
            $this->rotateLayersNinetyDegrees();
        }

        if (!$this->lookAheadMode) {
            $this->stabiliseLayers();
        }

        $this->logger->debug('done with this box ' . $this->box->getReference());

        return new PackedBox($this->box, $this->getPackedItemList());
    }

    /**
     * Pack items into an individual vertical layer.
     */
    protected function packLayer(ItemList $items, int $z, int $layerWidth, int $lengthLeft, int $depthLeft): ItemList
    {
        $this->layers[] = $layer = new PackedLayer();
        $prevItem = null;
        $x = $y = $rowLength = 0;
        $skippedItems = [];

        while ($items->count() > 0) {
            $itemToPack = $items->extract();

            //skip items that are simply too heavy or too large
            if (!$this->checkNonDimensionalConstraints($itemToPack)) {
                continue;
            }

            $orientatedItem = $this->getOrientationForItem($itemToPack, $prevItem, $items, count($skippedItems) + $items->count() === 0, $layerWidth - $x, $lengthLeft, $depthLeft, $rowLength, $x, $y, $z);

            if ($orientatedItem instanceof OrientatedItem) {
                $packedItem = PackedItem::fromOrientatedItem($orientatedItem, $x, $y, $z);
                $layer->insert($packedItem);

                $rowLength = max($rowLength, $orientatedItem->getLength());

                //Figure out if we can stack the next item vertically on top of this rather than side by side
                //e.g. when we've packed a tall item, and have just put a shorter one next to it.
                $stackableDepth = $layer->getDepth() - $orientatedItem->getDepth();
                $stackedZ = $z + $orientatedItem->getDepth();
                while ($items->count() > 0 && $this->checkNonDimensionalConstraints($items->top())) {
                    $stackedItem = $this->getOrientationForItem($items->top(), $prevItem, $items, $items->count() === 1, $orientatedItem->getWidth(), $orientatedItem->getLength(), $stackableDepth, $rowLength, $x, $y, $stackedZ);
                    if ($stackedItem) {
                        $layer->insert(PackedItem::fromOrientatedItem($stackedItem, $x, $y, $stackedZ));
                        $items->extract();
                        $stackableDepth -= $stackedItem->getDepth();
                        $stackedZ += $stackedItem->getDepth();
                    } else {
                        break;
                    }
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
     */
    public function stabiliseLayers(): void
    {
        $stabiliser = new LayerStabiliser();
        $stabiliser->setLogger($this->logger);
        $this->layers = $stabiliser->stabilise($this->layers);
    }

    protected function getOrientationForItem(
        Item $itemToPack,
        ?PackedItem $prevItem,
        ItemList $nextItems,
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

        $prevOrientatedItem = $prevItem ? $prevItem->toOrientatedItem() : null;
        $prevPackedItemList = $itemToPack instanceof ConstrainedPlacementItem ? $this->getPackedItemList() : new PackedItemList(); // don't calculate it if not going to be used

        return $this->orientatedItemFactory->getBestOrientation($itemToPack, $prevOrientatedItem, $nextItems, $isLastItem, $maxWidth, $maxLength, $maxDepth, $rowLength, $x, $y, $z, $prevPackedItemList);
    }

    /**
     * As well as purely dimensional constraints, there are other constraints that need to be met
     * e.g. weight limits or item-specific restrictions (e.g. max <x> batteries per box).
     */
    protected function checkNonDimensionalConstraints(Item $itemToPack): bool
    {
        $customConstraintsOK = true;
        if ($itemToPack instanceof ConstrainedItem) {
            $customConstraintsOK = $itemToPack->canBePackedInBox($this->getPackedItemList(), $this->box);
        }

        return $customConstraintsOK && $itemToPack->getWeight() <= $this->getRemainingWeightAllowed();
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
    protected function getPackedItemList(): PackedItemList
    {
        $packedItemList = new PackedItemList();
        foreach ($this->layers as $layer) {
            foreach ($layer->getItems() as $packedItem) {
                $packedItemList->insert($packedItem);
            }
        }

        return $packedItemList;
    }

    /**
     * Return the current packed depth.
     */
    protected function getCurrentPackedDepth(): int
    {
        $depth = 0;
        foreach ($this->layers as $layer) {
            $depth += $layer->getDepth();
        }

        return $depth;
    }

    private function getRemainingWeightAllowed(): int
    {
        $remainingWeightAllowed = $this->box->getMaxWeight() - $this->box->getEmptyWeight();
        foreach ($this->layers as $layer) {
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
