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
 * Layer packer.
 *
 * @internal
 * @author Doug Wright
 */
class LayerPacker implements LoggerAwareInterface
{
    /**
     * The logger instance.
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Box to pack items into.
     *
     * @var Box
     */
    private $box;

    /**
     * Whether the packer is in single-pass mode.
     *
     * @var bool
     */
    private $singlePassMode = false;

    /**
     * @var OrientatedItemFactory
     */
    private $orientatedItemFactory;

    /**
     * Constructor.
     */
    public function __construct(Box $box)
    {
        $this->box = $box;
        $this->logger = new NullLogger();

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

    public function setSinglePassMode(bool $singlePassMode): void
    {
        $this->singlePassMode = $singlePassMode;
        $this->orientatedItemFactory->setSinglePassMode($singlePassMode);
    }

    /**
     * Pack items into an individual vertical layer.
     */
    public function packLayer(ItemList &$items, PackedItemList $packedItemList, array $layers, int $z, int $layerWidth, int $lengthLeft, int $depthLeft, int $guidelineLayerDepth, bool $singlePassMode): PackedLayer
    {
        $layers[] = $layer = new PackedLayer();
        $prevItem = null;
        $x = $y = $rowLength = 0;
        $skippedItems = [];
        $remainingWeightAllowed = $this->getRemainingWeightAllowed($layers);

        while ($items->count() > 0) {
            $itemToPack = $items->extract();

            //skip items that are simply too heavy or too large
            if (!$this->checkNonDimensionalConstraints($itemToPack, $layers, $remainingWeightAllowed, $packedItemList)) {
                continue;
            }

            $orientatedItem = $this->orientatedItemFactory->getBestOrientation($itemToPack, $prevItem instanceof PackedItem ? $prevItem->toOrientatedItem() : null, $items, $layerWidth - $x, $lengthLeft, $depthLeft, $rowLength, $x, $y, $z, $packedItemList);

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
                    $stackedItem = $this->orientatedItemFactory->getBestOrientation($itemToTryStacking, $prevItem instanceof PackedItem ? $prevItem->toOrientatedItem() : null, $items, $orientatedItem->getWidth(), $orientatedItem->getLength(), $stackableDepth, $rowLength, $x, $y, $stackedZ, $packedItemList);
                    if ($stackedItem && $this->checkNonDimensionalConstraints($itemToTryStacking, $remainingWeightAllowed, $packedItemList)) {
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
     * As well as purely dimensional constraints, there are other constraints that need to be met
     * e.g. weight limits or item-specific restrictions (e.g. max <x> batteries per box).
     */
    private function checkNonDimensionalConstraints(Item $itemToPack, array $layers, int $remainingWeightAllowed, PackedItemList $packedItemList): bool
    {
        $customConstraintsOK = true;
        if ($itemToPack instanceof ConstrainedItem) {
            $customConstraintsOK = $itemToPack->canBePackedInBox($packedItemList, $this->box);
        }

        return $customConstraintsOK && $itemToPack->getWeight() <= $remainingWeightAllowed;
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
    private static function isSameDimensions(Item $itemA, Item $itemB): bool
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
