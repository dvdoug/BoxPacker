<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

/**
 * Actual packer.
 *
 * @author Doug Wright
 */
class WeightRedistributor implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * List of box sizes available to pack items into.
     *
     * @var BoxList
     */
    private $boxes;

    /**
     * Constructor.
     *
     * @param BoxList $boxList
     */
    public function __construct(BoxList $boxList)
    {
        $this->boxes = clone $boxList;
        $this->logger = new NullLogger();
    }

    /**
     * Given a solution set of packed boxes, repack them to achieve optimum weight distribution.
     *
     * @param PackedBoxList $originalBoxes
     *
     * @return PackedBoxList
     */
    public function redistributeWeight(PackedBoxList $originalBoxes)
    {
        $targetWeight = $originalBoxes->getMeanWeight();
        $this->logger->log(LogLevel::DEBUG, "repacking for weight distribution, weight variance {$originalBoxes->getWeightVariance()}, target weight {$targetWeight}");

        /** @var PackedBox[] $boxes */
        $boxes = iterator_to_array($originalBoxes);

        usort($boxes, function (PackedBox $boxA, PackedBox $boxB) {
            return $boxB->getWeight() - $boxA->getWeight();
        });

        do {
            $iterationSuccessful = false;

            foreach ($boxes as $a => &$boxA) {
                foreach ($boxes as $b => &$boxB) {
                    if ($b <= $a || $boxA->getWeight() === $boxB->getWeight()) {
                        continue; //no need to evaluate
                    }

                    $iterationSuccessful = $this->equaliseWeight($boxA, $boxB, $targetWeight);
                    if ($iterationSuccessful) {
                        $boxes = array_filter($boxes, function ($box) { //remove any now-empty boxes from the list
                            return $box instanceof PackedBox;
                        });
                        break 2;
                    }
                }
            }
        } while ($iterationSuccessful);

        //Combine back into a single list
        $packedBoxes = new PackedBoxList();
        $packedBoxes->insertFromArray($boxes);

        return $packedBoxes;
    }

    /**
     * Attempt to equalise weight distribution between 2 boxes.
     *
     * @param PackedBox $boxA
     * @param PackedBox $boxB
     * @param float     $targetWeight
     *
     * @return bool was the weight rebalanced?
     */
    private function equaliseWeight(PackedBox &$boxA, PackedBox &$boxB, $targetWeight)
    {
        $anyIterationSuccessful = false;

        if ($boxA->getWeight() > $boxB->getWeight()) {
            $overWeightBox = $boxA;
            $underWeightBox = $boxB;
        } else {
            $overWeightBox = $boxB;
            $underWeightBox = $boxA;
        }

        $overWeightBoxItems = $overWeightBox->getItems()->asArray();
        $underWeightBoxItems = $underWeightBox->getItems()->asArray();

        foreach ($overWeightBoxItems as $key => $overWeightItem) {
            if ($overWeightItem->getWeight() + $boxB->getWeight() > $targetWeight) {
                continue; // moving this item would harm more than help
            }

            $newLighterBoxes = $this->doVolumeRepack(array_merge($underWeightBoxItems, [$overWeightItem]));
            if (count($newLighterBoxes) !== 1) {
                continue; //only want to move this item if it still fits in a single box
            }

            $underWeightBoxItems[] = $overWeightItem;

            if (count($overWeightBoxItems) === 1) { //sometimes a repack can be efficient enough to eliminate a box
                $boxB = $newLighterBoxes->top();
                $boxA = null;

                return true;
            } else {
                unset($overWeightBoxItems[$key]);
                $newHeavierBoxes = $this->doVolumeRepack($overWeightBoxItems);
                if (count($newHeavierBoxes) !== 1) {
                    continue;
                }

                if ($this->didRepackActuallyHelp($boxA, $boxB, $newHeavierBoxes->top(), $newLighterBoxes->top())) {
                    $boxB = $newLighterBoxes->top();
                    $boxA = $newHeavierBoxes->top();
                    $anyIterationSuccessful = true;
                }
            }
        }

        return $anyIterationSuccessful;
    }

    /**
     * Do a volume repack of a set of items.
     *
     * @param array $items
     *
     * @return PackedBoxList
     */
    private function doVolumeRepack($items)
    {
        $packer = new Packer();
        $packer->setBoxes($this->boxes); // use the full set of boxes to allow smaller/larger for full efficiency
        $packer->setItems($items);

        return $packer->doVolumePacking();
    }

    /**
     * Not every attempted repack is actually helpful - sometimes moving an item between two otherwise identical
     * boxes, or sometimes the box used for the now lighter set of items actually weighs more when empty causing
     * an increase in total weight.
     *
     * @param PackedBox $oldBoxA
     * @param PackedBox $oldBoxB
     * @param PackedBox $newBoxA
     * @param PackedBox $newBoxB
     *
     * @return bool
     */
    private function didRepackActuallyHelp(PackedBox $oldBoxA, PackedBox $oldBoxB, PackedBox $newBoxA, PackedBox $newBoxB)
    {
        $oldList = new PackedBoxList();
        $oldList->insertFromArray([$oldBoxA, $oldBoxB]);

        $newList = new PackedBoxList();
        $newList->insertFromArray([$newBoxA, $newBoxB]);

        return $newList->getWeightVariance() < $oldList->getWeightVariance();
    }
}
