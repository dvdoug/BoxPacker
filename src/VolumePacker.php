<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

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
     * List of items temporarily skipped to be packed.
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
     * @var PackedLayer[]
     */
    protected $layers = [];

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
    public function pack()
    {
        $this->logger->debug("[EVALUATING BOX] {$this->box->getReference()}");

        while (count($this->items) > 0) {
            $layerStartDepth = $this->getCurrentPackedDepth();
            $this->packLayer($layerStartDepth, $this->boxWidth, $this->boxLength, $this->box->getInnerDepth() - $layerStartDepth);
        }

        if ($this->boxRotated) {
            $this->rotateLayersNinetyDegrees();
        }

        $this->stabiliseLayers();

        $this->logger->debug('done with this box');

        return PackedBox::fromPackedItemList($this->box, $this->getPackedItemList());
    }

    /**
     * Pack items into an individual vertical layer.
     *
     * @param int $startDepth
     * @param int $widthLeft
     * @param int $lengthLeft
     * @param int $depthLeft
     */
    protected function packLayer($startDepth, $widthLeft, $lengthLeft, $depthLeft)
    {
        $this->layers[] = $layer = new PackedLayer();
        $prevItem = null;
        $x = $y = $rowWidth = $rowLength = $layerDepth = 0;

        while (count($this->items) > 0) {
            $itemToPack = $this->items->extract();
            $nextItem = $this->getNextItem();

            //skip items that are simply too heavy or too large
            if (!$this->checkConstraints($itemToPack)) {
                $this->rebuildItemList();
                continue;
            }

            $orientatedItem = $this->getOrientationForItem($itemToPack, $prevItem, $nextItem, $this->hasItemsLeftToPack(), $widthLeft, $lengthLeft, $depthLeft);

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
                $this->tryAndStackItemsIntoSpace($layer, $prevItem, $nextItem, $orientatedItem->getWidth(), $orientatedItem->getLength(), $stackableDepth, $x, $y, $startDepth + $orientatedItem->getDepth());
                $x += $orientatedItem->getWidth();

                $prevItem = $packedItem;
                $this->rebuildItemList();
            } else {
                if (count($layer->getItems()) === 0) { // zero items on layer
                    $this->logger->debug("doesn't fit on layer even when empty, skipping for good");
                    continue;
                } elseif (count($this->items) > 0) { // skip for now, move on to the next item
                    $this->logger->debug("doesn't fit, skipping for now");
                    $this->skippedItems->insert($itemToPack);
                } elseif ($x > 0 && $lengthLeft >= min($itemToPack->getWidth(), $itemToPack->getLength())) {
                    $this->logger->debug('No more fit in width wise, resetting for new row');
                    $widthLeft += $rowWidth;
                    $lengthLeft -= $rowLength;
                    $y += $rowLength;
                    $x = $rowWidth = $rowLength = 0;
                    $this->rebuildItemList($itemToPack);
                    $prevItem = null;
                    continue;
                } else {
                    $this->logger->debug('no items fit, so starting next vertical layer');
                    $this->rebuildItemList($itemToPack);

                    return;
                }
            }
        }
    }

    /**
     * During packing, it is quite possible that layers have been created that aren't physically stable
     * i.e. they overhang the ones below.
     *
     * This function reorders them so that the ones with the greatest surface area are placed at the bottom
     */
    public function stabiliseLayers()
    {
        $stabiliser = new LayerStabiliser();
        $this->layers = $stabiliser->stabilise($this->layers);
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
        PackedItem $prevItem = null,
        Item $nextItem = null,
        $isLastItem,
        $maxWidth,
        $maxLength,
        $maxDepth
    ) {
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

        $orientatedItemFactory = new OrientatedItemFactory($itemToPack, $this->box);
        $orientatedItemFactory->setLogger($this->logger);
        $orientatedItem = $orientatedItemFactory->getBestOrientation($prevOrientatedItem, $nextItem, $isLastItem, $maxWidth, $maxLength, $maxDepth);

        return $orientatedItem;
    }

    /**
     * Figure out if we can stack the next item vertically on top of this rather than side by side
     * Used when we've packed a tall item, and have just put a shorter one next to it.
     *
     * @param PackedLayer     $layer
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
        PackedLayer $layer,
        PackedItem $prevItem = null,
        Item $nextItem = null,
        $maxWidth,
        $maxLength,
        $maxDepth,
        $x,
        $y,
        $z
    ) {
        while (count($this->items) > 0 && $this->checkNonDimensionalConstraints($this->items->top())) {
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
     * Check item generally fits into box.
     *
     * @param Item $itemToPack
     *
     * @return bool
     */
    protected function checkConstraints(
        Item $itemToPack
    ) {
        return $this->checkNonDimensionalConstraints($itemToPack) &&
               $this->checkDimensionalConstraints($itemToPack);
    }

    /**
     * As well as purely dimensional constraints, there are other constraints that need to be met
     * e.g. weight limits or item-specific restrictions (e.g. max <x> batteries per box).
     *
     * @param Item $itemToPack
     *
     * @return bool
     */
    protected function checkNonDimensionalConstraints(Item $itemToPack)
    {
        $weightOK = $itemToPack->getWeight() <= $this->remainingWeight;

        if ($itemToPack instanceof ConstrainedItem) {
            return $weightOK && $itemToPack->canBePackedInBox($this->getPackedItemList()->asItemList(), $this->box);
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
    protected function checkDimensionalConstraints(Item $itemToPack)
    {
        $orientatedItemFactory = new OrientatedItemFactory($itemToPack, $this->box);
        $orientatedItemFactory->setLogger($this->logger);

        return (bool) $orientatedItemFactory->getPossibleOrientationsInEmptyBox();
    }

    /**
     * Reintegrate skipped items into main list.
     *
     * @param Item|null $currentItem item from current iteration
     */
    protected function rebuildItemList(Item $currentItem = null)
    {
        if (count($this->items) === 0) {
            $this->items = $this->skippedItems;
            $this->skippedItems = new ItemList();
        }

        if ($currentItem instanceof Item) {
            $this->items->insert($currentItem);
        }
    }

    /**
     * Swap back width/length of the packed items to match orientation of the box if needed.
     */
    protected function rotateLayersNinetyDegrees()
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
     *
     * @return bool
     */
    protected function hasItemsLeftToPack()
    {
        return count($this->skippedItems) + count($this->items) === 0;
    }

    /**
     * Return next item in line for packing.
     *
     * @return Item|null
     */
    protected function getNextItem()
    {
        return count($this->items) ? $this->items->top() : null;
    }

    /**
     * Generate a single list of items packed.
     *
     * @return PackedItemList
     */
    protected function getPackedItemList()
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
     *
     * @return int
     */
    protected function getCurrentPackedDepth()
    {
        $depth = 0;
        foreach ($this->layers as $layer) {
            $depth += $layer->getDepth();
        }

        return $depth;
    }
}
