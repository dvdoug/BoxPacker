<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
namespace DVDoug\BoxPacker;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

/**
 * Actual packer
 * @author Doug Wright
 * @package BoxPacker
 */
class Packer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * List of items to be packed
     * @var ItemList
     */
    protected $items;

    /**
     * List of box sizes available to pack items into
     * @var BoxList
     */
    protected $boxes;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->items = new ItemList();
        $this->boxes = new BoxList();

        $this->logger = new NullLogger();
    }

    /**
     * Add item to be packed
     * @param Item $item
     * @param int  $qty
     */
    public function addItem(Item $item, $qty = 1)
    {
        for ($i = 0; $i < $qty; $i++) {
            $this->items->insert($item);
        }
        $this->logger->log(LogLevel::INFO, "added {$qty} x {$item->getDescription()}");
    }

    /**
     * Set a list of items all at once
     * @param \Traversable $items
     */
    public function setItems($items)
    {
        if ($items instanceof ItemList) {
            $this->items = clone $items;
        } elseif (is_array($items)) {
            $this->items = new ItemList();
            foreach ($items as $item) {
                $this->items->insert($item);
            }
        } else {
            throw new \RuntimeException('Not a valid list of items');
        }
    }

    /**
     * Add box size
     * @param Box $box
     */
    public function addBox(Box $box)
    {
        $this->boxes->insert($box);
        $this->logger->log(LogLevel::INFO, "added box {$box->getReference()}");
    }

    /**
     * Add a pre-prepared set of boxes all at once
     * @param BoxList $boxList
     */
    public function setBoxes(BoxList $boxList)
    {
        $this->boxes = clone $boxList;
    }

    /**
     * Pack items into boxes
     *
     * @throws \RuntimeException
     * @return PackedBoxList
     */
    public function pack()
    {
        $packedBoxes = $this->doVolumePacking();

        //If we have multiple boxes, try and optimise/even-out weight distribution
        if ($packedBoxes->count() > 1) {
            $packedBoxes = $this->redistributeWeight($packedBoxes);
        }

        $this->logger->log(LogLevel::INFO, "packing completed, {$packedBoxes->count()} boxes");
        return $packedBoxes;
    }

    /**
     * Pack items into boxes using the principle of largest volume item first
     *
     * @throws \RuntimeException
     * @return PackedBoxList
     */
    public function doVolumePacking()
    {

        $packedBoxes = new PackedBoxList;

        //Keep going until everything packed
        while ($this->items->count()) {
            $boxesToEvaluate = clone $this->boxes;
            $packedBoxesIteration = new PackedBoxList;

            //Loop through boxes starting with smallest, see what happens
            while (!$boxesToEvaluate->isEmpty()) {
                $box = $boxesToEvaluate->extract();
                $packedBox = $this->packIntoBox($box, clone $this->items);
                if ($packedBox->getItems()->count()) {
                    $packedBoxesIteration->insert($packedBox);

                    //Have we found a single box that contains everything?
                    if ($packedBox->getItems()->count() === $this->items->count()) {
                        break;
                    }
                }
            }

            //Check iteration was productive
            if ($packedBoxesIteration->isEmpty()) {
                throw new \RuntimeException('Item ' . $this->items->top()->getDescription() . ' is too large to fit into any box');
            }

            //Find best box of iteration, and remove packed items from unpacked list
            $bestBox = $packedBoxesIteration->top();
            $unPackedItems = $this->items->asArray();
            foreach (clone $bestBox->getItems() as $packedItem) {
                foreach ($unPackedItems as $unpackedKey => $unpackedItem) {
                    if ($packedItem === $unpackedItem) {
                        unset($unPackedItems[$unpackedKey]);
                        break;
                    }
                }
            }
            $unpackedItemList = new ItemList();
            foreach ($unPackedItems as $unpackedItem) {
                $unpackedItemList->insert($unpackedItem);
            }
            $this->items = $unpackedItemList;
            $packedBoxes->insert($bestBox);

        }

        return $packedBoxes;
    }

    /**
     * Given a solution set of packed boxes, repack them to achieve optimum weight distribution
     *
     * @param PackedBoxList $originalBoxes
     * @return PackedBoxList
     */
    public function redistributeWeight(PackedBoxList $originalBoxes)
    {

        $targetWeight = $originalBoxes->getMeanWeight();
        $this->logger->log(LogLevel::DEBUG, "repacking for weight distribution, weight variance {$originalBoxes->getWeightVariance()}, target weight {$targetWeight}");

        $packedBoxes = new PackedBoxList;

        $overWeightBoxes = [];
        $underWeightBoxes = [];
        foreach (clone $originalBoxes as $packedBox) {
            $boxWeight = $packedBox->getWeight();
            if ($boxWeight > $targetWeight) {
                $overWeightBoxes[] = $packedBox;
            } elseif ($boxWeight < $targetWeight) {
                $underWeightBoxes[] = $packedBox;
            } else {
                $packedBoxes->insert($packedBox); //target weight, so we'll keep these
            }
        }

        do { //Keep moving items from most overweight box to most underweight box
            $tryRepack = false;
            $this->logger->log(LogLevel::DEBUG, 'boxes under/over target: ' . count($underWeightBoxes) . '/' . count($overWeightBoxes));

            foreach ($underWeightBoxes as $u => $underWeightBox) {
                $this->logger->log(LogLevel::DEBUG, 'Underweight Box ' . $u);
                foreach ($overWeightBoxes as $o => $overWeightBox) {
                    $this->logger->log(LogLevel::DEBUG, 'Overweight Box ' . $o);
                    $overWeightBoxItems = $overWeightBox->getItems()->asArray();

                    //For each item in the heavier box, try and move it to the lighter one
                    foreach ($overWeightBoxItems as $oi => $overWeightBoxItem) {
                        $this->logger->log(LogLevel::DEBUG, 'Overweight Item ' . $oi);
                        if ($underWeightBox->getWeight() + $overWeightBoxItem->getWeight() > $targetWeight) {
                            $this->logger->log(LogLevel::DEBUG, 'Skipping item for hindering weight distribution');
                            continue; //skip if moving this item would hinder rather than help weight distribution
                        }

                        $newItemsForLighterBox = clone $underWeightBox->getItems();
                        $newItemsForLighterBox->insert($overWeightBoxItem);

                        $newLighterBoxPacker = new Packer(); //we may need a bigger box
                        $newLighterBoxPacker->setBoxes($this->boxes);
                        $newLighterBoxPacker->setItems($newItemsForLighterBox);
                        $this->logger->log(LogLevel::INFO, "[ATTEMPTING TO PACK LIGHTER BOX]");
                        $newLighterBox = $newLighterBoxPacker->doVolumePacking()->extract();

                        if ($newLighterBox->getItems()->count() === $newItemsForLighterBox->count()) { //new item fits
                            $this->logger->log(LogLevel::DEBUG, 'New item fits');
                            unset($overWeightBoxItems[$oi]); //now packed in different box

                            $newHeavierBoxPacker = new Packer(); //we may be able to use a smaller box
                            $newHeavierBoxPacker->setBoxes($this->boxes);
                            $newHeavierBoxPacker->setItems($overWeightBoxItems);

                            $this->logger->log(LogLevel::INFO, "[ATTEMPTING TO PACK HEAVIER BOX]");
                            $newHeavierBoxes = $newHeavierBoxPacker->doVolumePacking();
                            if (count($newHeavierBoxes) > 1) { //found an edge case in packing algorithm that *increased* box count
                                $this->logger->log(LogLevel::INFO, "[REDISTRIBUTING WEIGHT] Abandoning redistribution, because new packing is less efficient than original");
                                return $originalBoxes;
                            }

                            $overWeightBoxes[$o] = $newHeavierBoxes->extract();
                            $underWeightBoxes[$u] = $newLighterBox;

                            $tryRepack = true; //we did some work, so see if we can do even better
                            usort($overWeightBoxes, [$packedBoxes, 'reverseCompare']);
                            usort($underWeightBoxes, [$packedBoxes, 'reverseCompare']);
                            break 3;
                        }
                    }
                }
            }
        } while ($tryRepack);

        //Combine back into a single list
        $packedBoxes->insertFromArray($overWeightBoxes);
        $packedBoxes->insertFromArray($underWeightBoxes);

        return $packedBoxes;
    }


    /**
     * Pack as many items as possible into specific given box
     * @param Box      $box
     * @param ItemList $items
     * @return PackedBox packed box
     */
    public function packIntoBox(Box $box, ItemList $items)
    {
        $this->logger->log(LogLevel::DEBUG, "[EVALUATING BOX] {$box->getReference()}");

        $packedItems = new ItemList;
        $remainingDepth = $box->getInnerDepth();
        $remainingWeight = $box->getMaxWeight() - $box->getEmptyWeight();
        $remainingWidth = $box->getInnerWidth();
        $remainingLength = $box->getInnerLength();

        $layerWidth = $layerLength = $layerDepth = 0;
        while (!$items->isEmpty()) {

            $itemToPack = $items->top();

            if ($itemToPack->getDepth() > $remainingDepth || $itemToPack->getWeight() > $remainingWeight) {
                $items->extract();
                continue;
            }

            $this->logger->log(LogLevel::DEBUG, "evaluating item {$itemToPack->getDescription()}");
            $this->logger->log(LogLevel::DEBUG, "remaining width: {$remainingWidth}, length: {$remainingLength}, depth: {$remainingDepth}");
            $this->logger->log(LogLevel::DEBUG, "layerWidth: {$layerWidth}, layerLength: {$layerLength}, layerDepth: {$layerDepth}");

            $itemWidth = $itemToPack->getWidth();
            $itemLength = $itemToPack->getLength();

            $fitsSameGap = min($remainingWidth - $itemWidth, $remainingLength - $itemLength);
            $fitsRotatedGap = min($remainingWidth - $itemLength, $remainingLength - $itemWidth);

            if ($fitsSameGap >= 0 || $fitsRotatedGap >= 0) {

                $packedItems->insert($items->extract());
                $remainingWeight -= $itemToPack->getWeight();

                if ($fitsRotatedGap < 0 ||
                    ($fitsSameGap >= 0 && $fitsSameGap <= $fitsRotatedGap) ||
                    ($itemWidth <= $remainingWidth && !$items->isEmpty() && $items->top() == $itemToPack && $remainingLength >= 2 * $itemLength)) {
                    $this->logger->log(LogLevel::DEBUG, "fits (better) unrotated");
                    $remainingLength -= $itemLength;
                    $layerLength += $itemLength;
                    $layerWidth = max($itemWidth, $layerWidth);
                } else {
                    $this->logger->log(LogLevel::DEBUG, "fits (better) rotated");
                    $remainingLength -= $itemWidth;
                    $layerLength += $itemWidth;
                    $layerWidth = max($itemLength, $layerWidth);
                }
                $layerDepth = max($layerDepth, $itemToPack->getDepth()); //greater than 0, items will always be less deep

                //allow items to be stacked in place within the same footprint up to current layerdepth
                $maxStackDepth = $layerDepth - $itemToPack->getDepth();
                while (!$items->isEmpty()) {
                    $potentialStackItem = $items->top();
                    if ($potentialStackItem->getDepth() <= $maxStackDepth &&
                        $potentialStackItem->getWeight() <= $remainingWeight &&
                        $potentialStackItem->getWidth() <= $itemToPack->getWidth() &&
                        $potentialStackItem->getLength() <= $itemToPack->getLength()) {
                        $remainingWeight -= $potentialStackItem->getWeight();
                        $maxStackDepth -= $potentialStackItem->getDepth();
                        $packedItems->insert($items->extract());
                    } else {
                        break;
                    }
                }
            } else {
                if ($remainingWidth >= min($itemWidth, $itemLength) && $layerDepth > 0 && $layerWidth > 0 && $layerLength > 0) {
                    $this->logger->log(LogLevel::DEBUG, "No more fit in lengthwise, resetting for new row");
                    $remainingLength += $layerLength;
                    $remainingWidth -= $layerWidth;
                    $layerWidth = $layerLength = 0;
                    continue;
                }

                if ($remainingLength < min($itemWidth, $itemLength) || $layerDepth == 0) {
                    $this->logger->log(LogLevel::DEBUG, "doesn't fit on layer even when empty");
                    $items->extract();
                    continue;
                }

                $remainingWidth = $layerWidth ? min(floor($layerWidth * 1.1), $box->getInnerWidth()) : $box->getInnerWidth();
                $remainingLength = $layerLength ? min(floor($layerLength * 1.1), $box->getInnerLength()) : $box->getInnerLength();
                $remainingDepth -= $layerDepth;

                $layerWidth = $layerLength = $layerDepth = 0;
                $this->logger->log(LogLevel::DEBUG, "doesn't fit, so starting next vertical layer");
            }
        }
        $this->logger->log(LogLevel::DEBUG, "done with this box");
        return new PackedBox($box, $packedItems, $remainingWidth, $remainingLength, $remainingDepth, $remainingWeight);
    }

    /**
     * Pack as many items as possible into specific given box
     * @deprecated
     * @param Box      $box
     * @param ItemList $items
     * @return ItemList items packed into box
     */
    public function packBox(Box $box, ItemList $items)
    {
        $packedBox = $this->packIntoBox($box, $items);
        return $packedBox->getItems();
    }
}
