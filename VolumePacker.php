<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
namespace DVDoug\BoxPacker;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Actual packer
 * @author Doug Wright
 * @package BoxPacker
 */
class VolumePacker implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Box to pack items into
     * @var Box
     */
    protected $box;

    /**
     * List of items to be packed
     * @var ItemList
     */
    protected $items;

    /**
     * Remaining width of the box to pack items into
     * @var int
     */
    protected $widthLeft;

    /**
     * Remaining length of the box to pack items into
     * @var int
     */
    protected $lengthLeft;

    /**
     * Remaining depth of the box to pack items into
     * @var int
     */
    protected $depthLeft;

    /**
     * Remaining weight capacity of the box
     * @var int
     */
    protected $remainingWeight;

    /**
     * Used width inside box for packing items
     * @var int
     */
    protected $usedWidth = 0;

    /**
     * Used length inside box for packing items
     * @var int
     */
    protected $usedLength = 0;

    /**
     * Used depth inside box for packing items
     * @var int
     */
    protected $usedDepth = 0;

    /**
     * @var int
     */
    protected $layerWidth = 0;

    /**
     * @var int
     */
    protected $layerLength = 0;

    /**
     * @var int
     */
    protected $layerDepth = 0;

    /**
     * Constructor
     *
     * @param Box      $box
     * @param ItemList $items
     */
    public function __construct(Box $box, ItemList $items)
    {
        $this->logger = new NullLogger();

        $this->box = $box;
        $this->items = $items;

        $this->depthLeft = $this->box->getInnerDepth();
        $this->remainingWeight = $this->box->getMaxWeight() - $this->box->getEmptyWeight();
        $this->widthLeft = $this->box->getInnerWidth();
        $this->lengthLeft = $this->box->getInnerLength();
    }

    /**
     * Pack as many items as possible into specific given box
     * @return PackedBox packed box
     */
    public function pack()
    {
        $this->logger->debug("[EVALUATING BOX] {$this->box->getReference()}");

        $packedItems = new ItemList;

        $this->layerWidth = $this->layerLength = $this->layerDepth = 0;

        $prevItem = null;

        while (!$this->items->isEmpty()) {

            $itemToPack = $this->items->extract();
            $nextItem = !$this->items->isEmpty() ? $this->items->top() : null;

            //skip items that are simply too heavy or too large
            if (!$this->checkConstraints($itemToPack, $packedItems, $prevItem, $nextItem)) {
                continue;
            }

            $orientatedItem = $this->getOrientationForItem($itemToPack, $prevItem, $nextItem, $this->widthLeft, $this->lengthLeft, $this->depthLeft);

            if ($orientatedItem) {

                $packedItems->insert($orientatedItem->getItem());
                $this->remainingWeight -= $orientatedItem->getItem()->getWeight();

                $this->lengthLeft -= $orientatedItem->getLength();
                $this->layerLength += $orientatedItem->getLength();
                $this->layerWidth = max($orientatedItem->getWidth(), $this->layerWidth);

                $this->layerDepth = max($this->layerDepth, $orientatedItem->getDepth()); //greater than 0, items will always be less deep

                $this->usedLength = max($this->usedLength, $this->layerLength);
                $this->usedWidth = max($this->usedWidth, $this->layerWidth);

                //allow items to be stacked in place within the same footprint up to current layerdepth
                $stackableDepth = $this->layerDepth - $orientatedItem->getDepth();
                $this->tryAndStackItemsIntoSpace($packedItems, $prevItem, $nextItem, $orientatedItem->getWidth(), $orientatedItem->getLength(), $stackableDepth);

                $prevItem = $orientatedItem;

                if ($this->items->isEmpty()) {
                    $this->usedDepth += $this->layerDepth;
                }
            } else {

                $prevItem = null;

                if ($this->widthLeft >= min($itemToPack->getWidth(), $itemToPack->getLength()) && $this->isLayerStarted()) {
                    $this->logger->debug("No more fit in lengthwise, resetting for new row");
                    $this->lengthLeft += $this->layerLength;
                    $this->widthLeft -= $this->layerWidth;
                    $this->layerWidth = $this->layerLength = 0;
                    $this->items->insert($itemToPack);
                    continue;
                } elseif ($this->lengthLeft < min($itemToPack->getWidth(), $itemToPack->getLength()) || $this->layerDepth == 0) {
                    $this->logger->debug("doesn't fit on layer even when empty");
                    $this->usedDepth += $this->layerDepth;
                    continue;
                }

                $this->widthLeft = $this->layerWidth ? min(floor($this->layerWidth * 1.1), $this->box->getInnerWidth()) : $this->box->getInnerWidth();
                $this->lengthLeft = $this->layerLength ? min(floor($this->layerLength * 1.1), $this->box->getInnerLength()) : $this->box->getInnerLength();
                $this->depthLeft -= $this->layerDepth;
                $this->usedDepth += $this->layerDepth;

                $this->layerWidth = $this->layerLength = $this->layerDepth = 0;
                $this->logger->debug("doesn't fit, so starting next vertical layer");
                $this->items->insert($itemToPack);
            }
        }
        $this->logger->debug("done with this box");
        return new PackedBox(
            $this->box,
            $packedItems,
            $this->widthLeft,
            $this->lengthLeft,
            $this->depthLeft,
            $this->remainingWeight,
            $this->usedWidth,
            $this->usedLength,
            $this->usedDepth);
    }

    /**
     * @param Item $itemToPack
     * @param OrientatedItem|null $prevItem
     * @param Item|null $nextItem
     * @param int $maxWidth
     * @param int $maxLength
     * @param int $maxDepth
     *
     * @return OrientatedItem|false
     */
    protected function getOrientationForItem(
        Item $itemToPack,
        OrientatedItem $prevItem = null,
        Item $nextItem = null,
        $maxWidth, $maxLength,
        $maxDepth
    ) {
        $this->logger->debug(
            "evaluating item {$itemToPack->getDescription()} for fit",
            [
                'item' => $itemToPack,
                'space' => [
                    'maxWidth'    => $maxWidth,
                    'maxLength'   => $maxLength,
                    'maxDepth'    => $maxDepth,
                    'layerWidth'  => $this->layerWidth,
                    'layerLength' => $this->layerLength,
                    'layerDepth'  => $this->layerDepth
                ]
            ]
        );

        $orientatedItemFactory = new OrientatedItemFactory();
        $orientatedItemFactory->setLogger($this->logger);
        $orientatedItem = $orientatedItemFactory->getBestOrientation($this->box, $itemToPack, $prevItem, $nextItem, $maxWidth, $maxLength, $maxDepth);

        return $orientatedItem;
    }

    /**
     * Figure out if we can stack the next item vertically on top of this rather than side by side
     * Used when we've packed a tall item, and have just put a shorter one next to it
     *
     * @param ItemList       $packedItems
     * @param OrientatedItem $prevItem
     * @param Item           $nextItem
     * @param int            $maxWidth
     * @param int            $maxLength
     * @param int            $maxDepth
     */
    protected function tryAndStackItemsIntoSpace(
        ItemList $packedItems,
        OrientatedItem $prevItem = null,
        Item $nextItem = null,
        $maxWidth,
        $maxLength,
        $maxDepth
    ) {
        while (!$this->items->isEmpty() && $this->remainingWeight >= $this->items->top()->getWeight()) {
            $stackedItem = $this->getOrientationForItem(
                $this->items->top(),
                $prevItem,
                $nextItem,
                $maxWidth,
                $maxLength,
                $maxDepth
            );
            if ($stackedItem) {
                $this->remainingWeight -= $this->items->top()->getWeight();
                $maxDepth -= $stackedItem->getDepth();
                $packedItems->insert($this->items->extract());
            } else {
                break;
            }
        }
    }

    /**
     * @return bool
     */
    protected function isLayerStarted()
    {
        return $this->layerWidth > 0 && $this->layerLength > 0 && $this->layerDepth > 0;
    }


    /**
     * Check item generally fits into box
     *
     * @param Item            $itemToPack
     * @param ItemList  $packedItems
     * @param OrientatedItem|null $prevItem
     * @param Item|null       $nextItem
     *
     * @return bool
     */
    protected function checkConstraints(
        Item $itemToPack,
        ItemList $packedItems,
        OrientatedItem $prevItem = null,
        Item $nextItem = null
    ) {
        return $this->checkNonDimensionalConstraints($itemToPack, $packedItems) &&
               $this->checkDimensionalConstraints($itemToPack, $prevItem, $nextItem);
    }

    /**
     * As well as purely dimensional constraints, there are other constraints that need to be met
     * e.g. weight limits or item-specific restrictions (e.g. max <x> batteries per box)
     *
     * @param Item     $itemToPack
     * @param ItemList $packedItems
     *
     * @return bool
     */
    protected function checkNonDimensionalConstraints(Item $itemToPack, ItemList $packedItems)
    {
        $weightOK = $itemToPack->getWeight() <= $this->remainingWeight;

        if ($itemToPack instanceof ConstrainedItem) {
            return $weightOK && $itemToPack->canBePackedInBox(clone $packedItems, $this->box);
        }

        return $weightOK;
    }

    /**
     * Check the item physically fits in the box (at all)
     *
     * @param Item            $itemToPack
     * @param OrientatedItem|null $prevItem
     * @param Item|null       $nextItem
     *
     * @return bool
     */
    protected function checkDimensionalConstraints(Item $itemToPack, OrientatedItem $prevItem = null, Item $nextItem = null)
    {
        return !!$this->getOrientationForItem(
            $itemToPack,
            $prevItem,
            $nextItem,
            $this->box->getInnerWidth(),
            $this->box->getInnerLength(),
            $this->box->getInnerDepth()
        );
    }
}
