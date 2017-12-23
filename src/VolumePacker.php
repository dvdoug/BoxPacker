<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Actual packer.
 *
 * @author Doug Wright
 */
class VolumePacker implements LoggerAwareInterface
{
    use LoggerAwareTrait;

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
     * List of items to be packed.
     *
     * @var ItemList
     */
    protected $skippedItems;

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
     * Constructor.
     *
     * @param Box      $box
     * @param ItemList $items
     */
    public function __construct(Box $box, ItemList $items)
    {
        $this->box = $box;
        $this->items = $items;

        $this->boxWidth = max($this->box->getInnerWidth(), $this->box->getInnerLength());
        $this->boxLength = min($this->box->getInnerWidth(), $this->box->getInnerLength());
        $this->remainingWeight = $this->box->getMaxWeight() - $this->box->getEmptyWeight();
        $this->skippedItems = new ItemList();
        $this->logger = new NullLogger();

        // we may have just rotated the box for packing purposes, record if we did
        if ($this->box->getInnerWidth() != $this->boxWidth || $this->box->getInnerLength() != $this->boxLength) {
            $this->boxRotated = true;
        }
    }

    /**
     * Pack as many items as possible into specific given box.
     *
     * @return PackedBox packed box
     */
    public function pack(): PackedBox
    {
        $this->logger->debug("[EVALUATING BOX] {$this->box->getReference()}");

        $packedItems = new PackedItemList();
        $prevItem = null;

        $x = $y = $z = $rowWidth = $rowLength = $layerWidth = $layerLength = $layerDepth = 0;

        $packingWidthLeft = $this->boxWidth;
        $packingLengthLeft = $this->boxLength;
        $packingDepthLeft = $this->box->getInnerDepth();

        while (count($this->items) > 0) {
            $itemToPack = $this->items->extract();
            $nextItem = count($this->items) ? $this->items->top() : null;

            //skip items that are simply too heavy or too large
            if (!$this->checkConstraints($itemToPack, $packedItems)) {
                $this->rebuildItemList();
                continue;
            }

            $orientatedItem = $this->getOrientationForItem($itemToPack, $prevItem, $nextItem, $this->hasItemsLeftToPack(), $packingWidthLeft, $packingLengthLeft, $packingDepthLeft);

            if ($orientatedItem instanceof OrientatedItem) {
                $packedItem = PackedItem::fromOrientatedItem($orientatedItem, $x, $y, $z);
                $packedItems->insert($packedItem);
                $this->remainingWeight -= $orientatedItem->getItem()->getWeight();
                $packingWidthLeft -= $orientatedItem->getWidth();

                $rowWidth += $orientatedItem->getWidth();
                $rowLength = max($rowLength, $orientatedItem->getLength());
                $layerDepth = max($layerDepth, $orientatedItem->getDepth());

                //allow items to be stacked in place within the same footprint up to current layer depth
                $stackableDepth = $layerDepth - $orientatedItem->getDepth();
                $this->tryAndStackItemsIntoSpace($packedItems, $prevItem, $nextItem, $orientatedItem->getWidth(), $orientatedItem->getLength(), $stackableDepth, $x, $y, $z + $orientatedItem->getDepth());
                $x += $orientatedItem->getWidth();

                $prevItem = $packedItem;

                $this->rebuildItemList();
            } else {
                if ($layerWidth == 0 && $layerDepth == 0) { // zero items on layer
                    $this->logger->debug("doesn't fit on layer even when empty, skipping for good");
                    $prevItem = null;
                    continue;
                } elseif (count($this->items) > 0) { // skip for now, move on to the next item
                    $this->logger->debug("doesn't fit, skipping for now");
                    $this->skippedItems->insert($itemToPack);
                } elseif ($x > 0 && $packingLengthLeft >= min($itemToPack->getWidth(), $itemToPack->getLength())) {
                    $this->logger->debug('No more fit in width wise, resetting for new row');
                    $layerWidth = max($layerWidth, $rowWidth);
                    $layerLength += $rowLength;
                    $packingWidthLeft += $rowWidth;
                    $packingLengthLeft -= $rowLength;
                    $y += $rowLength;
                    $x = $rowWidth = $rowLength = 0;
                    $this->rebuildItemList();
                    $this->items->insert($itemToPack);
                    $prevItem = null;
                    continue;
                } else {
                    $this->logger->debug('no items fit, so starting next vertical layer');

                    $layerWidth = max($layerWidth, $rowWidth);
                    $layerLength += $rowLength;
                    $packingWidthLeft = $rowWidth ? min(intval($layerWidth * 1.1), $this->boxWidth) : $this->boxWidth;
                    $packingLengthLeft = $rowLength ? min(intval($layerLength * 1.1), $this->boxLength) : $this->boxLength;
                    $packingDepthLeft -= $layerDepth;

                    $z += $layerDepth;
                    $x = $y = $rowWidth = $rowLength = $layerWidth = $layerLength = $layerDepth = 0;

                    $this->rebuildItemList();
                    $this->items->insert($itemToPack);
                    $prevItem = null;
                }
            }
        }
        $this->logger->debug('done with this box');

        return $this->createPackedBox($packedItems);
    }

    /**
     * @param Item            $itemToPack
     * @param PackedItem|null $prevItem
     * @param Item|null       $nextItem
     * @param bool            $isLastItem
     * @param int             $maxWidth
     * @param int             $maxLength
     * @param int             $maxDepth
     *
     * @return OrientatedItem|null
     */
    protected function getOrientationForItem(
        Item $itemToPack,
        ?PackedItem $prevItem,
        ?Item $nextItem,
        bool $isLastItem,
        int $maxWidth,
        int $maxLength,
        int $maxDepth
    ): ?OrientatedItem {
        $this->logger->debug(
            "evaluating item {$itemToPack->getDescription()} for fit",
            [
                'item'  => $itemToPack,
                'space' => [
                    'maxWidth'    => $maxWidth,
                    'maxLength'   => $maxLength,
                    'maxDepth'    => $maxDepth,
                ],
            ]
        );

        $prevOrientatedItem = $prevItem ? $prevItem->toOrientatedItem() : null;

        $orientatedItemFactory = new OrientatedItemFactory();
        $orientatedItemFactory->setLogger($this->logger);
        $orientatedItem = $orientatedItemFactory->getBestOrientation($this->box, $itemToPack, $prevOrientatedItem, $nextItem, $isLastItem, $maxWidth, $maxLength, $maxDepth);

        return $orientatedItem;
    }

    /**
     * Figure out if we can stack the next item vertically on top of this rather than side by side
     * Used when we've packed a tall item, and have just put a shorter one next to it.
     *
     * @param PackedItemList  $packedItems
     * @param PackedItem|null $prevItem
     * @param Item|null       $nextItem
     * @param int             $maxWidth
     * @param int             $maxLength
     * @param int             $maxDepth
     * @param int             $x
     * @param int             $y
     * @param int             $z
     */
    protected function tryAndStackItemsIntoSpace(
        PackedItemList $packedItems,
        ?PackedItem $prevItem,
        ?Item $nextItem,
        int $maxWidth,
        int $maxLength,
        int $maxDepth,
        int $x,
        int $y,
        int $z
    ): void {
        while (count($this->items) > 0 && $this->checkNonDimensionalConstraints($this->items->top(), $packedItems)) {
            $stackedItem = $this->getOrientationForItem(
                $this->items->top(),
                $prevItem,
                $nextItem,
                $this->items->count() === 1,
                $maxWidth,
                $maxLength,
                $maxDepth
            );
            if ($stackedItem) {
                $this->remainingWeight -= $this->items->top()->getWeight();
                $packedItems->insert(PackedItem::fromOrientatedItem($stackedItem, $x, $y, $z));
                $this->items->extract();
                $maxDepth -= $stackedItem->getDepth();
                $z += $stackedItem->getDepth();
            } else {
                break;
            }
        }
    }

    /**
     * Check item generally fits into box.
     *
     * @param Item           $itemToPack
     * @param PackedItemList $packedItems
     *
     * @return bool
     */
    protected function checkConstraints(
        Item $itemToPack,
        PackedItemList $packedItems
    ): bool {
        return $this->checkNonDimensionalConstraints($itemToPack, $packedItems) &&
               $this->checkDimensionalConstraints($itemToPack);
    }

    /**
     * As well as purely dimensional constraints, there are other constraints that need to be met
     * e.g. weight limits or item-specific restrictions (e.g. max <x> batteries per box).
     *
     * @param Item           $itemToPack
     * @param PackedItemList $packedItems
     *
     * @return bool
     */
    protected function checkNonDimensionalConstraints(Item $itemToPack, PackedItemList $packedItems): bool
    {
        $weightOK = $itemToPack->getWeight() <= $this->remainingWeight;

        if ($itemToPack instanceof ConstrainedItem) {
            return $weightOK && $itemToPack->canBePackedInBox($packedItems, $this->box);
        }

        return $weightOK;
    }

    /**
     * Check the item physically fits in the box (at all).
     *
     * @param Item $itemToPack
     *
     * @return bool
     */
    protected function checkDimensionalConstraints(Item $itemToPack): bool
    {
        $orientatedItemFactory = new OrientatedItemFactory();
        $orientatedItemFactory->setLogger($this->logger);

        return (bool) $orientatedItemFactory->getPossibleOrientationsInEmptyBox($itemToPack, $this->box);
    }

    /**
     * Reintegrate skipped items into main list when nothing left to process.
     */
    protected function rebuildItemList(): void
    {
        if (count($this->items) === 0) {
            $this->items = $this->skippedItems;
            $this->skippedItems = new ItemList();
        }
    }

    /**
     * @param PackedItemList $packedItems
     *
     * @return PackedBox
     */
    protected function createPackedBox(PackedItemList $packedItems): PackedBox
    {
        //if we rotated the box for packing, need to swap back width/length of the packed items
        if ($this->boxRotated) {
            $items = iterator_to_array($packedItems, false);
            $packedItems = new PackedItemList();
            /** @var PackedItem $item */
            foreach ($items as $item) {
                $packedItems->insert(
                    new PackedItem(
                        $item->getItem(),
                        $item->getY(),
                        $item->getX(),
                        $item->getZ(),
                        $item->getLength(),
                        $item->getWidth(),
                        $item->getDepth()
                    )
                );
            }
        }

        return new PackedBox($this->box, $packedItems);
    }

    /**
     * Are there items left to pack?
     *
     * @return bool
     */
    protected function hasItemsLeftToPack(): bool
    {
        return count($this->skippedItems) + count($this->items) === 0;
    }
}
