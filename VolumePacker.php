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

        $layerWidth = $layerLength = $layerDepth = 0;

        $prevItem = null;

        while (!$this->items->isEmpty()) {

            $itemToPack = $this->items->extract();

            //skip items that are simply too heavy
            if (!$this->checkNonDimensionalConstraints($itemToPack, $packedItems)) {
                continue;
            }

            $this->logger->debug(
                "evaluating item {$itemToPack->getDescription()} for fit",
                [
                    'item' => $itemToPack,
                    'space' => [
                        'widthLeft'   => $this->widthLeft,
                        'lengthLeft'  => $this->lengthLeft,
                        'depthLeft'   => $this->depthLeft,
                        'layerWidth'  => $layerWidth,
                        'layerLength' => $layerLength,
                        'layerDepth'  => $layerDepth
                    ]
                ]
            );

            $nextItem = !$this->items->isEmpty() ? $this->items->top() : null;

            $orientatedItemFactory = new OrientatedItemFactory();
            $orientatedItemFactory->setLogger($this->logger);
            $orientatedItem = $orientatedItemFactory->getBestOrientation($this->box, $itemToPack, $prevItem, $nextItem, $this->widthLeft, $this->lengthLeft, $this->depthLeft);

            if ($orientatedItem) {

                $packedItems->insert($orientatedItem->getItem());
                $this->remainingWeight -= $itemToPack->getWeight();

                $this->lengthLeft -= $orientatedItem->getLength();
                $layerLength += $orientatedItem->getLength();
                $layerWidth = max($orientatedItem->getWidth(), $layerWidth);

                $layerDepth = max($layerDepth, $orientatedItem->getDepth()); //greater than 0, items will always be less deep

                $this->usedLength = max($this->usedLength, $layerLength);
                $this->usedWidth = max($this->usedWidth, $layerWidth);

                //allow items to be stacked in place within the same footprint up to current layerdepth
                $stackableDepth = $layerDepth - $orientatedItem->getDepth();
                $this->tryAndStackItemsIntoSpace($packedItems, $prevItem, $nextItem, $orientatedItem->getWidth(), $orientatedItem->getLength(), $stackableDepth);

                $prevItem = $orientatedItem;

                if ($this->items->isEmpty()) {
                    $this->usedDepth += $layerDepth;
                }
            } else {

                $prevItem = null;

                if ($this->widthLeft >= min($itemToPack->getWidth(), $itemToPack->getLength()) && $this->isLayerStarted($layerWidth, $layerLength, $layerDepth)) {
                    $this->logger->debug("No more fit in lengthwise, resetting for new row");
                    $this->lengthLeft += $layerLength;
                    $this->widthLeft -= $layerWidth;
                    $layerWidth = $layerLength = 0;
                    $this->items->insert($itemToPack);
                    continue;
                } elseif ($this->lengthLeft < min($itemToPack->getWidth(), $itemToPack->getLength()) || $layerDepth == 0) {
                    $this->logger->debug("doesn't fit on layer even when empty");
                    $this->usedDepth += $layerDepth;
                    continue;
                }

                $this->widthLeft = $layerWidth ? min(floor($layerWidth * 1.1), $this->box->getInnerWidth()) : $this->box->getInnerWidth();
                $this->lengthLeft = $layerLength ? min(floor($layerLength * 1.1), $this->box->getInnerLength()) : $this->box->getInnerLength();
                $this->depthLeft -= $layerDepth;
                $this->usedDepth += $layerDepth;

                $layerWidth = $layerLength = $layerDepth = 0;
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
        $orientatedItemFactory = new OrientatedItemFactory();
        $orientatedItemFactory->setLogger($this->logger);

        while (!$this->items->isEmpty() && $this->remainingWeight >= $this->items->top()->getWeight()) {
            $stackedItem = $orientatedItemFactory->getBestOrientation(
                $this->box,
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
     * @param int $layerWidth
     * @param int $layerLength
     * @param int $layerDepth
     * @return bool
     */
    protected function isLayerStarted($layerWidth, $layerLength, $layerDepth)
    {
        return $layerWidth > 0 && $layerLength > 0 && $layerDepth > 0;
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
}
