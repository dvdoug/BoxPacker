<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use function array_merge;
use function iterator_to_array;
use function max;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use function sort;

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
    public function packLayer(ItemList &$items, PackedItemList $packedItemList, int $startX, int $startY, int $startZ, int $widthForLayer, int $lengthForLayer, int $depthForLayer, int $guidelineLayerDepth, bool $considerStability): PackedLayer
    {
        $layer = new PackedLayer();
        $x = $startX;
        $y = $startY;
        $z = $startZ;
        $lengthLeft = $lengthForLayer;
        $rowLength = 0;
        $prevItem = null;
        $skippedItems = [];
        $remainingWeightAllowed = $this->box->getMaxWeight() - $this->box->getEmptyWeight() - $packedItemList->getWeight();

        while ($items->count() > 0) {
            $itemToPack = $items->extract();

            //skip items that will never fit e.g. too heavy
            if (!$this->checkNonDimensionalConstraints($itemToPack, $remainingWeightAllowed, $packedItemList)) {
                continue;
            }

            $orientatedItem = $this->orientatedItemFactory->getBestOrientation($itemToPack, $prevItem, $items, $widthForLayer - $x, $lengthLeft, $depthForLayer, $rowLength, $x, $y, $z, $packedItemList, $considerStability);

            if ($orientatedItem instanceof OrientatedItem) {
                $packedItem = PackedItem::fromOrientatedItem($orientatedItem, $x, $y, $z);
                $layer->insert($packedItem);
                $remainingWeightAllowed -= $itemToPack->getWeight();
                $packedItemList->insert($packedItem);

                $rowLength = max($rowLength, $packedItem->getLength());

                //Figure out if we can stack the next item vertically on top of this rather than side by side
                //e.g. when we've packed a tall item, and have just put a shorter one next to it.
                $this->packVerticallyInsideItemFootprint($layer, $packedItem, $packedItemList, $items, $remainingWeightAllowed, $guidelineLayerDepth, $rowLength, $x, $y, $z, $considerStability);

                $prevItem = $orientatedItem;

                //Having now placed an item, there is space *within the same row* along the length. Pack into that.
                if (!$this->singlePassMode && $rowLength - $orientatedItem->getLength() > 0) {
                    $layer->merge($this->packLayer($items, $packedItemList, $x, $y + $orientatedItem->getLength(), $z, $widthForLayer, $rowLength - $orientatedItem->getLength(), $depthForLayer, $layer->getDepth(), $considerStability));
                }

                $x += $packedItem->getWidth();

                if ($items->count() === 0 && $skippedItems) {
                    $items = ItemList::fromArray(array_merge($skippedItems, iterator_to_array($items)), true);
                    $skippedItems = [];
                }
                continue;
            }

            if ($items->count() > 0) { // skip for now, move on to the next item
                $this->logger->debug("doesn't fit, skipping for now");
                $skippedItems[] = $itemToPack;
                // abandon here if next item is the same, no point trying to keep going. Last time is not skipped, need that to trigger appropriate reset logic
                while ($items->count() > 1 && static::isSameDimensions($itemToPack, $items->top())) {
                    $skippedItems[] = $items->extract();
                }
                continue;
            }

            if ($x > $startX) {
                $this->logger->debug('No more fit in width wise, resetting for new row');
                $lengthLeft -= $rowLength;
                $y += $rowLength;
                $x = $startX;
                $rowLength = 0;
                $skippedItems[] = $itemToPack;
                $items = ItemList::fromArray($skippedItems, true);
                $skippedItems = [];
                $prevItem = null;
                continue;
            }

            $this->logger->debug('no items fit, so starting next vertical layer');
            $skippedItems[] = $itemToPack;

            $items = ItemList::fromArray(array_merge($skippedItems, iterator_to_array($items)), true);

            return $layer;
        }

        return $layer;
    }

    private function packVerticallyInsideItemFootprint(PackedLayer $layer, PackedItem $packedItem, PackedItemList $packedItemList, ItemList &$items, int &$remainingWeightAllowed, int $guidelineLayerDepth, int $rowLength, int $x, int $y, int $z, bool $considerStability): void
    {
        $stackableDepth = ($guidelineLayerDepth ?: $layer->getDepth()) - $packedItem->getDepth();
        $stackedZ = $z + $packedItem->getDepth();
        $stackSkippedItems = [];
        $stackedItem = $packedItem->toOrientatedItem();
        while ($stackableDepth > 0 && $items->count() > 0) {
            $itemToTryStacking = $items->extract();

            //skip items that will never fit
            if (!$this->checkNonDimensionalConstraints($itemToTryStacking, $remainingWeightAllowed, $packedItemList)) {
                continue;
            }

            $stackedItem = $this->orientatedItemFactory->getBestOrientation($itemToTryStacking, $stackedItem, $items, $packedItem->getWidth(), $packedItem->getLength(), $stackableDepth, $rowLength, $x, $y, $stackedZ, $packedItemList, $considerStability);
            if ($stackedItem) {
                $packedStackedItem = PackedItem::fromOrientatedItem($stackedItem, $x, $y, $stackedZ);
                $layer->insert($packedStackedItem);
                $remainingWeightAllowed -= $itemToTryStacking->getWeight();
                $packedItemList->insert($packedStackedItem);
                $stackableDepth -= $stackedItem->getDepth();
                $stackedZ += $stackedItem->getDepth();
                continue;
            }

            $stackSkippedItems[] = $itemToTryStacking;
            // abandon here if next item is the same, no point trying to keep going
            while ($items->count() > 0 && static::isSameDimensions($itemToTryStacking, $items->top())) {
                $stackSkippedItems[] = $items->extract();
            }
        }
        if ($stackSkippedItems) {
            $items = ItemList::fromArray(array_merge($stackSkippedItems, iterator_to_array($items)), true);
        }
    }

    /**
     * As well as purely dimensional constraints, there are other constraints that need to be met
     * e.g. weight limits or item-specific restrictions (e.g. max <x> batteries per box).
     */
    private function checkNonDimensionalConstraints(Item $itemToPack, int $remainingWeightAllowed, PackedItemList $packedItemList): bool
    {
        $customConstraintsOK = true;
        if ($itemToPack instanceof ConstrainedItem && !$this->box instanceof WorkingVolume) {
            $customConstraintsOK = $itemToPack->canBePackedInBox($packedItemList, $this->box);
        }

        return $customConstraintsOK && $itemToPack->getWeight() <= $remainingWeightAllowed;
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
