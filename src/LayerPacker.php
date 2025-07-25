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

use function array_merge;
use function iterator_to_array;
use function max;
use function sort;

/**
 * Layer packer.
 * @internal
 */
class LayerPacker implements LoggerAwareInterface
{
    private LoggerInterface $logger;

    private bool $singlePassMode = false;

    private readonly OrientatedItemFactory $orientatedItemFactory;

    private bool $beStrictAboutItemOrdering = false;

    private bool $isBoxRotated = false;

    public function __construct(private readonly Box $box)
    {
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

    public function setBoxIsRotated(bool $boxIsRotated): void
    {
        $this->isBoxRotated = $boxIsRotated;
        $this->orientatedItemFactory->setBoxIsRotated($boxIsRotated);
    }

    public function beStrictAboutItemOrdering(bool $beStrict): void
    {
        $this->beStrictAboutItemOrdering = $beStrict;
    }

    /**
     * Pack items into an individual vertical layer.
     */
    public function packLayer(ItemList &$items, PackedItemList $packedItemList, int $startX, int $startY, int $startZ, int $widthForLayer, int $lengthForLayer, int $depthForLayer, int $guidelineLayerDepth, bool $considerStability, ?OrientatedItem $firstItem): PackedLayer
    {
        $layer = new PackedLayer();
        $x = $startX;
        $y = $startY;
        $z = $startZ;
        $rowLength = 0;
        $prevItem = null;
        $skippedItems = [];

        while ($items->count() > 0) {
            $itemToPack = $items->extract();

            // skip items that will never fit e.g. too heavy
            if ($itemToPack->getWeight() > ($this->box->getMaxWeight() - $this->box->getEmptyWeight() - $packedItemList->getWeight())) {
                continue;
            }

            if ($firstItem instanceof OrientatedItem && $firstItem->item === $itemToPack) {
                $orientatedItem = $firstItem;
                $firstItem = null;
            } else {
                $orientatedItem = $this->orientatedItemFactory->getBestOrientation($itemToPack, $prevItem, $items, $widthForLayer - $x, $lengthForLayer - $y, $depthForLayer, $rowLength, $x, $y, $z, $packedItemList, $considerStability);
            }

            if ($orientatedItem instanceof OrientatedItem) {
                $packedItem = PackedItem::fromOrientatedItem($orientatedItem, $x, $y, $z);
                $layer->insert($packedItem);
                $packedItemList->insert($packedItem);

                $rowLength = max($rowLength, $packedItem->length);
                $prevItem = $orientatedItem;

                // Figure out if we can stack items on top of this rather than side by side
                // e.g. when we've packed a tall item, and have just put a shorter one next to it.
                $stackableDepth = ($guidelineLayerDepth ?: $layer->getDepth()) - $packedItem->depth;
                if ($stackableDepth > 0) {
                    $stackedLayer = $this->packLayer($items, $packedItemList, $x, $y, $z + $packedItem->depth, $x + $packedItem->width, $y + $packedItem->length, $stackableDepth, $stackableDepth, $considerStability, null);
                    $layer->merge($stackedLayer);
                }

                $x += $packedItem->width;

                // might be space available lengthwise across the width of this item, up to the current layer length
                $layer->merge($this->packLayer($items, $packedItemList, $x - $packedItem->width, $y + $packedItem->length, $z, $x, $y + $rowLength, $depthForLayer, $layer->getDepth(), $considerStability, null));

                if ($items->count() === 0 && $skippedItems) {
                    $items = ItemList::fromArray(array_merge($skippedItems, iterator_to_array($items)), true);
                    $skippedItems = [];
                }

                continue;
            }

            if (!$this->beStrictAboutItemOrdering && $items->count() > 0) { // skip for now, move on to the next item
                $this->logger->debug("doesn't fit, skipping for now");
                $skippedItems[] = $itemToPack;
                // abandon here if next item is the same, no point trying to keep going. Last time is not skipped, need that to trigger appropriate reset logic
                while ($items->count() > 1 && self::isSameDimensions($itemToPack, $items->top())) {
                    $skippedItems[] = $items->extract();
                }
                continue;
            }

            if ($x > $startX) {
                $this->logger->debug('No more fit in width wise, resetting for new row');
                $y += $rowLength;
                $x = $startX;
                $rowLength = 0;
                $skippedItems[] = $itemToPack;
                $items = ItemList::fromArray(array_merge($skippedItems, iterator_to_array($items)), true);
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
