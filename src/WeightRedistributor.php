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
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use SplObjectStorage;

use function array_filter;
use function array_map;
use function array_merge;
use function array_sum;
use function count;
use function iterator_to_array;
use function usort;
use function assert;

/**
 * Actual packer.
 * @internal
 */
class WeightRedistributor implements LoggerAwareInterface
{
    private LoggerInterface $logger;

    private BoxList $boxes;

    /**
     * @var SplObjectStorage<Box, int>
     */
    private SplObjectStorage $boxQuantitiesAvailable;

    private PackedBoxSorter $packedBoxSorter;

    public function __construct(BoxList $boxList, PackedBoxSorter $packedBoxSorter, SplObjectStorage $boxQuantitiesAvailable)
    {
        $this->boxes = $boxList;
        $this->packedBoxSorter = $packedBoxSorter;
        $this->boxQuantitiesAvailable = $boxQuantitiesAvailable;
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Given a solution set of packed boxes, repack them to achieve optimum weight distribution.
     */
    public function redistributeWeight(PackedBoxList $originalBoxes): PackedBoxList
    {
        $targetWeight = $originalBoxes->getMeanItemWeight();
        $this->logger->log(LogLevel::DEBUG, "repacking for weight distribution, weight variance {$originalBoxes->getWeightVariance()}, target weight {$targetWeight}");

        $boxes = iterator_to_array($originalBoxes);

        usort($boxes, static fn (PackedBox $boxA, PackedBox $boxB) => $boxB->getWeight() <=> $boxA->getWeight());

        do {
            $iterationSuccessful = false;

            foreach ($boxes as $a => &$boxA) {
                foreach ($boxes as $b => &$boxB) {
                    if ($b <= $a || $boxA->getWeight() === $boxB->getWeight()) {
                        continue; // no need to evaluate
                    }

                    $iterationSuccessful = $this->equaliseWeight($boxA, $boxB, $targetWeight);
                    if ($iterationSuccessful) {
                        $boxes = array_filter($boxes, static fn (?PackedBox $box) => $box instanceof PackedBox); // remove any now-empty boxes from the list
                        break 2;
                    }
                }
            }
        } while ($iterationSuccessful);

        // Combine back into a single list
        $packedBoxes = new PackedBoxList($this->packedBoxSorter);
        $packedBoxes->insertFromArray($boxes);

        return $packedBoxes;
    }

    /**
     * Attempt to equalise weight distribution between 2 boxes.
     *
     * @return bool was the weight rebalanced?
     */
    private function equaliseWeight(PackedBox &$boxA, PackedBox &$boxB, float $targetWeight): bool
    {
        $anyIterationSuccessful = false;

        if ($boxA->getWeight() > $boxB->getWeight()) {
            $overWeightBox = $boxA;
            $underWeightBox = $boxB;
        } else {
            $overWeightBox = $boxB;
            $underWeightBox = $boxA;
        }

        $overWeightBoxItems = $overWeightBox->getItems()->asItemArray();
        $underWeightBoxItems = $underWeightBox->getItems()->asItemArray();

        foreach ($overWeightBoxItems as $key => $overWeightItem) {
            if (!self::wouldRepackActuallyHelp($overWeightBoxItems, $overWeightItem, $underWeightBoxItems, $targetWeight)) {
                continue; // moving this item would harm more than help
            }

            $newLighterBoxes = $this->doVolumeRepack(array_merge($underWeightBoxItems, [$overWeightItem]), $underWeightBox->getBox());
            if ($newLighterBoxes->count() !== 1) {
                continue; // only want to move this item if it still fits in a single box
            }

            $underWeightBoxItems[] = $overWeightItem;

            if (count($overWeightBoxItems) === 1) { // sometimes a repack can be efficient enough to eliminate a box
                $boxB = $newLighterBoxes->top();
                $boxA = null;
                $this->boxQuantitiesAvailable[$underWeightBox->getBox()] = $this->boxQuantitiesAvailable[$underWeightBox->getBox()] - 1;
                $this->boxQuantitiesAvailable[$overWeightBox->getBox()] = $this->boxQuantitiesAvailable[$overWeightBox->getBox()] + 1;

                return true;
            }

            unset($overWeightBoxItems[$key]);
            $newHeavierBoxes = $this->doVolumeRepack($overWeightBoxItems, $overWeightBox->getBox());
            if (count($newHeavierBoxes) !== 1) {
                assert(true, 'Could not pack n-1 items into box, even though n were previously in it');
                continue;
            }

            $this->boxQuantitiesAvailable[$overWeightBox->getBox()] = $this->boxQuantitiesAvailable[$overWeightBox->getBox()] + 1;
            $this->boxQuantitiesAvailable[$underWeightBox->getBox()] = $this->boxQuantitiesAvailable[$underWeightBox->getBox()] + 1;
            $this->boxQuantitiesAvailable[$newHeavierBoxes->top()->getBox()] = $this->boxQuantitiesAvailable[$newHeavierBoxes->top()->getBox()] - 1;
            $this->boxQuantitiesAvailable[$newLighterBoxes->top()->getBox()] = $this->boxQuantitiesAvailable[$newLighterBoxes->top()->getBox()] - 1;
            $underWeightBox = $boxB = $newLighterBoxes->top();
            $overWeightBox = $boxA = $newHeavierBoxes->top();

            $anyIterationSuccessful = true;
        }

        return $anyIterationSuccessful;
    }

    /**
     * Do a volume repack of a set of items.
     * @param iterable<Item> $items
     */
    private function doVolumeRepack(iterable $items, Box $currentBox): PackedBoxList
    {
        $packer = new Packer();
        $packer->setLogger($this->logger);
        $packer->setBoxes($this->boxes); // use the full set of boxes to allow smaller/larger for full efficiency
        foreach ($this->boxes as $box) {
            $packer->setBoxQuantity($box, $this->boxQuantitiesAvailable[$box]);
        }
        $packer->setBoxQuantity($currentBox, $this->boxQuantitiesAvailable[$currentBox] + 1);
        $packer->setItems($items);

        return $packer->doBasicPacking(true);
    }

    /**
     * Not every attempted repack is actually helpful - sometimes moving an item between two otherwise identical
     * boxes, or sometimes the box used for the now lighter set of items actually weighs more when empty causing
     * an increase in total weight.
     * @param array<Item> $overWeightBoxItems
     * @param array<Item> $underWeightBoxItems
     */
    private static function wouldRepackActuallyHelp(array $overWeightBoxItems, Item $overWeightItem, array $underWeightBoxItems, float $targetWeight): bool
    {
        $overWeightItemsWeight = array_sum(array_map(static fn (Item $item) => $item->getWeight(), $overWeightBoxItems));
        $underWeightItemsWeight = array_sum(array_map(static fn (Item $item) => $item->getWeight(), $underWeightBoxItems));

        if ($overWeightItem->getWeight() + $underWeightItemsWeight > $targetWeight) {
            return false;
        }

        $oldVariance = self::calculateVariance($overWeightItemsWeight, $underWeightItemsWeight);
        $newVariance = self::calculateVariance($overWeightItemsWeight - $overWeightItem->getWeight(), $underWeightItemsWeight + $overWeightItem->getWeight());

        return $newVariance < $oldVariance;
    }

    private static function calculateVariance(int $boxAWeight, int $boxBWeight): float
    {
        return ($boxAWeight - (($boxAWeight + $boxBWeight) / 2)) ** 2; // don't need to calculate B and ÷ 2, for a 2-item population the difference from mean is the same for each box
    }
}
