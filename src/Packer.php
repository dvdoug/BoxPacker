<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use function array_merge;
use function count;
use DVDoug\BoxPacker\Exception\NoBoxesAvailableException;
use const PHP_INT_MAX;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use function usort;
use WeakMap;

/**
 * Actual packer.
 */
class Packer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected int $maxBoxesToBalanceWeight = 12;

    protected ItemList $items;

    protected BoxList $boxes;

    /** @var WeakMap<Box, int> */
    protected WeakMap $boxQuantitiesAvailable;

    protected PackedBoxSorter $packedBoxSorter;

    protected bool $throwOnUnpackableItem = true;

    public function __construct()
    {
        $this->items = new ItemList();
        $this->boxes = new BoxList();
        $this->packedBoxSorter = new DefaultPackedBoxSorter();
        $this->boxQuantitiesAvailable = new WeakMap();

        $this->logger = new NullLogger();
    }

    /**
     * Add item to be packed.
     */
    public function addItem(Item $item, int $qty = 1): void
    {
        $this->items->insert($item, $qty);
        $this->logger->log(LogLevel::INFO, "added {$qty} x {$item->getDescription()}", ['item' => $item]);
    }

    /**
     * Set a list of items all at once.
     * @param iterable<Item>|ItemList $items
     */
    public function setItems(iterable $items): void
    {
        if ($items instanceof ItemList) {
            $this->items = clone $items;
        } else {
            $this->items = new ItemList();
            foreach ($items as $item) {
                $this->items->insert($item);
            }
        }
    }

    /**
     * Add box size.
     */
    public function addBox(Box $box): void
    {
        $this->boxes->insert($box);
        $this->setBoxQuantity($box, $box instanceof LimitedSupplyBox ? $box->getQuantityAvailable() : PHP_INT_MAX);
        $this->logger->log(LogLevel::INFO, "added box {$box->getReference()}", ['box' => $box]);
    }

    /**
     * Add a pre-prepared set of boxes all at once.
     */
    public function setBoxes(BoxList $boxList): void
    {
        $this->boxes = $boxList;
        foreach ($this->boxes as $box) {
            $this->setBoxQuantity($box, $box instanceof LimitedSupplyBox ? $box->getQuantityAvailable() : PHP_INT_MAX);
        }
    }

    /**
     * Set the quantity of this box type available.
     */
    public function setBoxQuantity(Box $box, int $qty): void
    {
        $this->boxQuantitiesAvailable[$box] = $qty;
    }

    /**
     * Number of boxes at which balancing weight is deemed not worth the extra computation time.
     */
    public function getMaxBoxesToBalanceWeight(): int
    {
        return $this->maxBoxesToBalanceWeight;
    }

    /**
     * Number of boxes at which balancing weight is deemed not worth the extra computation time.
     */
    public function setMaxBoxesToBalanceWeight(int $maxBoxesToBalanceWeight): void
    {
        $this->maxBoxesToBalanceWeight = $maxBoxesToBalanceWeight;
    }

    public function setPackedBoxSorter(PackedBoxSorter $packedBoxSorter): void
    {
        $this->packedBoxSorter = $packedBoxSorter;
    }

    public function throwOnUnpackableItem(bool $throwOnUnpackableItem): void
    {
        $this->throwOnUnpackableItem = $throwOnUnpackableItem;
    }

    /**
     * Return the items that haven't been packed.
     */
    public function getUnpackedItems(): ItemList
    {
        return $this->items;
    }

    /**
     * Pack items into boxes.
     */
    public function pack(): PackedBoxList
    {
        $packedBoxes = $this->doVolumePacking();

        //If we have multiple boxes, try and optimise/even-out weight distribution
        if ($packedBoxes->count() > 1 && $packedBoxes->count() <= $this->maxBoxesToBalanceWeight) {
            $redistributor = new WeightRedistributor($this->boxes, $this->packedBoxSorter, $this->boxQuantitiesAvailable);
            $redistributor->setLogger($this->logger);
            $packedBoxes = $redistributor->redistributeWeight($packedBoxes);
        }

        $this->logger->log(LogLevel::INFO, "[PACKING COMPLETED], {$packedBoxes->count()} boxes");

        return $packedBoxes;
    }

    /**
     * Pack items into boxes using the principle of largest volume item first.
     *
     * @throws NoBoxesAvailableException
     */
    public function doVolumePacking(bool $enforceSingleBox = false): PackedBoxList
    {
        $packedBoxes = new PackedBoxList($this->packedBoxSorter);

        //Keep going until everything packed
        while ($this->items->count()) {
            $packedBoxesIteration = [];

            //Loop through boxes starting with smallest, see what happens
            foreach ($this->getBoxList($enforceSingleBox) as $box) {
                $volumePacker = new VolumePacker($box, $this->items);
                $volumePacker->setLogger($this->logger);
                $packedBox = $volumePacker->pack();
                if ($packedBox->getItems()->count()) {
                    $packedBoxesIteration[] = $packedBox;

                    //Have we found a single box that contains everything?
                    if ($packedBox->getItems()->count() === $this->items->count()) {
                        break;
                    }
                }
            }

            if (count($packedBoxesIteration) > 0) {
                //Find best box of iteration, and remove packed items from unpacked list
                usort($packedBoxesIteration, [$this->packedBoxSorter, 'compare']);
                $bestBox = $packedBoxesIteration[0];

                $this->items->removePackedItems($bestBox->getItems());

                $packedBoxes->insert($bestBox);
                --$this->boxQuantitiesAvailable[$bestBox->getBox()];
            } elseif ($this->throwOnUnpackableItem) {
                throw new NoBoxesAvailableException("No boxes could be found for item '{$this->items->top()->getDescription()}'", $this->items);
            } else {
                break;
            }
        }

        return $packedBoxes;
    }

    /**
     * Get a "smart" ordering of the boxes to try packing items into. The initial BoxList is already sorted in order
     * so that the smallest boxes are evaluated first, but this means that time is spent on boxes that cannot possibly
     * hold the entire set of items due to volume limitations. These should be evaluated first.
     *
     * @return iterable<Box>
     */
    protected function getBoxList(bool $enforceSingleBox = false): iterable
    {
        $itemVolume = 0;
        foreach ($this->items as $item) {
            $itemVolume += $item->getWidth() * $item->getLength() * $item->getDepth();
        }

        $preferredBoxes = [];
        $otherBoxes = [];
        foreach ($this->boxes as $box) {
            if ($this->boxQuantitiesAvailable[$box] > 0) {
                if ($box->getInnerWidth() * $box->getInnerLength() * $box->getInnerDepth() >= $itemVolume) {
                    $preferredBoxes[] = $box;
                } elseif (!$enforceSingleBox) {
                    $otherBoxes[] = $box;
                }
            }
        }

        return array_merge($preferredBoxes, $otherBoxes);
    }
}
