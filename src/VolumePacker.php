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
     * List of items temporarily skipped to be packed.
     *
     * @var array
     */
    protected $skippedItems = [];

    /**
     * Remaining weight capacity of the box.
     *
     * @var int
     */
    protected $remainingWeight;

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
        $this->remainingWeight = $this->box->getMaxWeight() - $this->box->getEmptyWeight();
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
            $this->packLayer($layerStartDepth, $this->boxWidth, $this->boxLength, $this->box->getInnerDepth() - $layerStartDepth);
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
    protected function packLayer(int $startDepth, int $widthLeft, int $lengthLeft, int $depthLeft): void
    {
        $this->layers[] = $layer = new PackedLayer();
        $prevItem = null;
        $x = $y = $rowWidth = $rowLength = $layerDepth = 0;

        while ($this->items->count() > 0) {
            $itemToPack = $this->items->extract();

            //skip items that are simply too heavy or too large
            if (!$this->checkNonDimensionalConstraints($itemToPack)) {
                continue;
            }

            $orientatedItem = $this->getOrientationForItem($itemToPack, $prevItem, $this->items, !$this->hasItemsLeftToPack(), $widthLeft, $lengthLeft, $depthLeft, $rowLength, $x, $y, $startDepth);

            if ($orientatedItem instanceof OrientatedItem) {
                $packedItem = PackedItem::fromOrientatedItem($orientatedItem, $x, $y, $startDepth);
                $layer->insert($packedItem);
                $this->remainingWeight -= $orientatedItem->getItem()->getWeight();
                $widthLeft -= $orientatedItem->getWidth();

                $rowWidth += $orientatedItem->getWidth();
                $rowLength = max($rowLength, $orientatedItem->getLength());
                $layerDepth = max($layerDepth, $orientatedItem->getDepth());

                //allow items to be stacked in place within the same footprint up to current layer depth
                $stackableDepth = $layerDepth - $orientatedItem->getDepth();
                $this->tryAndStackItemsIntoSpace($layer, $prevItem, $this->items, $orientatedItem->getWidth(), $orientatedItem->getLength(), $stackableDepth, $x, $y, $startDepth + $orientatedItem->getDepth(), $rowLength);
                $x += $orientatedItem->getWidth();

                $prevItem = $packedItem;
                if ($this->items->count() === 0) {
                    $this->rebuildItemList();
                }
            } elseif (count($layer->getItems()) === 0) { // zero items on layer
                $this->logger->debug("doesn't fit on layer even when empty, skipping for good");
                continue;
            } elseif ($widthLeft > 0 && $this->items->count() > 0) { // skip for now, move on to the next item
                $this->logger->debug("doesn't fit, skipping for now");
                $this->skippedItems[] = $itemToPack;
                // abandon here if next item is the same, no point trying to keep going. Last time is not skipped, need that to trigger appropriate reset logic
                while ($this->items->count() > 2 && static::isSameDimensions($itemToPack, $this->items->top())) {
                    $this->skippedItems[] = $this->items->extract();
                }
            } elseif ($x > 0 && $lengthLeft >= min($itemToPack->getWidth(), $itemToPack->getLength(), $itemToPack->getDepth())) {
                $this->logger->debug('No more fit in width wise, resetting for new row');
                $widthLeft += $rowWidth;
                $lengthLeft -= $rowLength;
                $y += $rowLength;
                $x = $rowWidth = $rowLength = 0;
                $this->skippedItems[] = $itemToPack;
                $this->rebuildItemList();
                $prevItem = null;
                continue;
            } else {
                $this->logger->debug('no items fit, so starting next vertical layer');
                $this->skippedItems[] = $itemToPack;
                $this->rebuildItemList();

                return;
            }
        }
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

        $orientatedItemDecision = $this->orientatedItemFactory->getBestOrientation($itemToPack, $prevOrientatedItem, $nextItems, $isLastItem, $maxWidth, $maxLength, $maxDepth, $rowLength, $x, $y, $z, $prevPackedItemList);

        return $orientatedItemDecision;
    }

    /**
     * Figure out if we can stack the next item vertically on top of this rather than side by side
     * Used when we've packed a tall item, and have just put a shorter one next to it.
     */
    protected function tryAndStackItemsIntoSpace(
        PackedLayer $layer,
        ?PackedItem $prevItem,
        ItemList $nextItems,
        int $maxWidth,
        int $maxLength,
        int $maxDepth,
        int $x,
        int $y,
        int $z,
        int $rowLength
    ): void {
        while ($this->items->count() > 0 && $this->checkNonDimensionalConstraints($this->items->top())) {
            $stackedItem = $this->getOrientationForItem(
                $this->items->top(),
                $prevItem,
                $nextItems,
                $this->items->count() === 1,
                $maxWidth,
                $maxLength,
                $maxDepth,
                $rowLength,
                $x,
                $y,
                $z
            );
            if ($stackedItem) {
                $this->remainingWeight -= $this->items->top()->getWeight();
                $layer->insert(PackedItem::fromOrientatedItem($stackedItem, $x, $y, $z));
                $this->items->extract();
                $maxDepth -= $stackedItem->getDepth();
                $z += $stackedItem->getDepth();
            } else {
                break;
            }
        }
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

        return $customConstraintsOK && $itemToPack->getWeight() <= $this->remainingWeight;
    }

    /**
     * Reintegrate skipped items into main list.
     */
    protected function rebuildItemList(): void
    {
        $this->items = ItemList::fromArray(array_merge($this->skippedItems, iterator_to_array($this->items)), true);
        $this->skippedItems = [];
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
     * Are there items left to pack?
     */
    protected function hasItemsLeftToPack(): bool
    {
        return count($this->skippedItems) + $this->items->count() > 0;
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
