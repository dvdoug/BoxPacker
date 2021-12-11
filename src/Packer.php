<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use function array_merge;
use function array_pop;
use function count;
use DVDoug\BoxPacker\Exception\NoBoxesAvailableException;
use const PHP_INT_MAX;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use function usort;
use WeakMap;

/**
 * Actual packer.
 */
class Packer implements LoggerAwareInterface
{
    private LoggerInterface $logger;

    protected int $maxBoxesToBalanceWeight = 12;

    protected ItemList $items;

    protected BoxList $boxes;

    /** @var WeakMap<Box, int> */
    protected WeakMap $boxQuantitiesAvailable;

    protected PackedBoxSorter $packedBoxSorter;

    protected bool $throwOnUnpackableItem = true;

    private bool $beStrictAboutItemOrdering = false;

    public function __construct()
    {
        $this->items = new ItemList();
        $this->boxes = new BoxList();
        $this->packedBoxSorter = new DefaultPackedBoxSorter();
        $this->boxQuantitiesAvailable = new WeakMap();

        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
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

    public function beStrictAboutItemOrdering(bool $beStrict): void
    {
        $this->beStrictAboutItemOrdering = $beStrict;
    }

    /**
     * Return the items that haven't been packed.
     */
    public function getUnpackedItems(): ItemList
    {
        return $this->items;
    }

    /**
     * Pack items into boxes using built-in heuristics for the best solution.
     */
    public function pack(): PackedBoxList
    {
        $this->logger->log(LogLevel::INFO, '[PACKING STARTED]');

        $packedBoxes = $this->doBasicPacking();

        //If we have multiple boxes, try and optimise/even-out weight distribution
        if (!$this->beStrictAboutItemOrdering && $packedBoxes->count() > 1 && $packedBoxes->count() <= $this->maxBoxesToBalanceWeight) {
            $redistributor = new WeightRedistributor($this->boxes, $this->packedBoxSorter, $this->boxQuantitiesAvailable);
            $redistributor->setLogger($this->logger);
            $packedBoxes = $redistributor->redistributeWeight($packedBoxes);
        }

        $this->logger->log(LogLevel::INFO, "[PACKING COMPLETED], {$packedBoxes->count()} boxes");

        return $packedBoxes;
    }

    /**
     * @internal
     */
    public function doBasicPacking(bool $enforceSingleBox = false): PackedBoxList
    {
        $packedBoxes = new PackedBoxList($this->packedBoxSorter);

        //Keep going until everything packed
        while ($this->items->count()) {
            $packedBoxesIteration = [];

            //Loop through boxes starting with smallest, see what happens
            foreach ($this->getBoxList($enforceSingleBox) as $box) {
                $volumePacker = new VolumePacker($box, $this->items);
                $volumePacker->setLogger($this->logger);
                $volumePacker->beStrictAboutItemOrdering($this->beStrictAboutItemOrdering);
                $packedBox = $volumePacker->pack();
                if ($packedBox->getItems()->count()) {
                    $packedBoxesIteration[] = $packedBox;

                    //Have we found a single box that contains everything?
                    if ($packedBox->getItems()->count() === $this->items->count()) {
                        $this->logger->log(LogLevel::DEBUG, "Single box found for remaining {$this->items->count()} items");
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
                $this->logger->log(LogLevel::INFO, "{$this->items->count()} unpackable items found");
                break;
            }
        }

        return $packedBoxes;
    }

    /**
     * Pack items into boxes returning "all" possible box combination permutations.
     * Use with caution (will be slow) with a large number of box types!
     *
     * @return PackedBoxList[]
     */
    public function packAllPermutations(): array
    {
        $this->logger->log(LogLevel::INFO, '[PACKING STARTED (all permutations)]');

        $boxQuantitiesAvailable = clone $this->boxQuantitiesAvailable;

        $wipPermutations = [['permutation' => new PackedBoxList($this->packedBoxSorter), 'itemsLeft' => $this->items]];
        $completedPermutations = [];

        //Keep going until everything packed
        while ($wipPermutations) {
            $wipPermutation = array_pop($wipPermutations);
            $remainingBoxQuantities = clone $boxQuantitiesAvailable;
            foreach ($wipPermutation['permutation'] as $packedBox) {
                --$remainingBoxQuantities[$packedBox->getBox()];
            }
            if ($wipPermutation['itemsLeft']->count() === 0) {
                $completedPermutations[] = $wipPermutation['permutation'];
                continue;
            }

            $additionalPermutationsForThisPermutation = [];
            foreach ($this->boxes as $box) {
                if ($remainingBoxQuantities[$box] > 0) {
                    $volumePacker = new VolumePacker($box, $wipPermutation['itemsLeft']);
                    $volumePacker->setLogger($this->logger);
                    $packedBox = $volumePacker->pack();
                    if ($packedBox->getItems()->count()) {
                        $additionalPermutationsForThisPermutation[] = $packedBox;
                    }
                }
            }

            if (count($additionalPermutationsForThisPermutation) > 0) {
                foreach ($additionalPermutationsForThisPermutation as $additionalPermutationForThisPermutation) {
                    $newPermutation = clone $wipPermutation['permutation'];
                    $newPermutation->insert($additionalPermutationForThisPermutation);
                    $itemsRemainingOnPermutation = clone $wipPermutation['itemsLeft'];
                    $itemsRemainingOnPermutation->removePackedItems($additionalPermutationForThisPermutation->getItems());
                    $wipPermutations[] = ['permutation' => $newPermutation, 'itemsLeft' => $itemsRemainingOnPermutation];
                }
            } elseif ($this->throwOnUnpackableItem) {
                throw new NoBoxesAvailableException("No boxes could be found for item '{$wipPermutation['itemsLeft']->top()->getDescription()}'", $wipPermutation['itemsLeft']);
            } else {
                $this->logger->log(LogLevel::INFO, "{$this->items->count()} unpackable items found");
                if ($wipPermutation['permutation']->count() > 0) { // don't treat initial empty permutation as completed
                    $completedPermutations[] = $wipPermutation['permutation'];
                }
            }
        }

        $this->logger->log(LogLevel::INFO, '[PACKING COMPLETED], ' . count($completedPermutations) . ' permutations');

        foreach ($completedPermutations as $completedPermutation) {
            foreach ($completedPermutation as $packedBox) {
                $this->items->removePackedItems($packedBox->getItems());
            }
        }

        return $completedPermutations;
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
        $this->logger->log(LogLevel::INFO, 'Determining box search pattern', ['enforceSingleBox' => $enforceSingleBox]);
        $itemVolume = 0;
        foreach ($this->items as $item) {
            $itemVolume += $item->getWidth() * $item->getLength() * $item->getDepth();
        }
        $this->logger->log(LogLevel::DEBUG, 'Item volume', ['itemVolume' => $itemVolume]);

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

        $this->logger->log(LogLevel::INFO, 'Box search pattern complete', ['preferredBoxCount' => count($preferredBoxes), 'otherBoxCount' => count($otherBoxes)]);

        return array_merge($preferredBoxes, $otherBoxes);
    }
}
