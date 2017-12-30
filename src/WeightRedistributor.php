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
    protected $boxes;

    /**
     * Boxes over the target weight.
     *
     * @var PackedBox[]
     */
    protected $overWeightBoxes = [];

    /**
     * Boxes exactly at the target weight.
     *
     * @var PackedBox[]
     */
    protected $targetWeightBoxes = [];

    /**
     * Boxes under the target weight.
     *
     * @var PackedBox[]
     */
    protected $underWeightBoxes = [];

    /**
     * Constructor.
     *
     * @param BoxList $boxList
     */
    public function __construct(BoxList $boxList)
    {
        $this->boxes = $boxList;
        $this->logger = new NullLogger();
    }

    /**
     * Given a solution set of packed boxes, repack them to achieve optimum weight distribution.
     *
     * @param PackedBoxList $originalBoxes
     *
     * @return PackedBoxList
     */
    public function redistributeWeight(PackedBoxList $originalBoxes): PackedBoxList
    {
        $targetWeight = $originalBoxes->getMeanWeight();
        $this->logger->log(LogLevel::DEBUG, "repacking for weight distribution, weight variance {$originalBoxes->getWeightVariance()}, target weight {$targetWeight}");

        $this->classifyBoxes($originalBoxes, $targetWeight);

        do { //Keep moving items from most overweight box to most underweight box
            $tryRepack = false;
            $this->logger->log(LogLevel::DEBUG, 'boxes under/over target: '.count($this->underWeightBoxes).'/'.count($this->overWeightBoxes));

            usort($this->overWeightBoxes, [$this, 'sortMoreSpaceFirst']);
            usort($this->underWeightBoxes, [$this, 'sortMoreSpaceFirst']);

            foreach ($this->underWeightBoxes as $u => $underWeightBox) {
                $this->logger->log(LogLevel::DEBUG, 'Underweight Box '.$u);
                foreach ($this->overWeightBoxes as $o => $overWeightBox) {
                    $this->logger->log(LogLevel::DEBUG, 'Overweight Box '.$o);
                    $overWeightBoxItems = $overWeightBox->getItems()->asItemArray();

                    //For each item in the heavier box, try and move it to the lighter one
                    /** @var Item $overWeightBoxItem */
                    foreach ($overWeightBoxItems as $oi => $overWeightBoxItem) {
                        $this->logger->log(LogLevel::DEBUG, 'Overweight Item '.$oi);
                        if ($underWeightBox->getWeight() + $overWeightBoxItem->getWeight() > $targetWeight) {
                            $this->logger->log(LogLevel::DEBUG, 'Skipping item for hindering weight distribution');
                            continue; //skip if moving this item would hinder rather than help weight distribution
                        }

                        $newItemsForLighterBox = $underWeightBox->getItems()->asItemArray();
                        $newItemsForLighterBox[] = $overWeightBoxItem;

                        $newLighterBoxPacker = new Packer(); //we may need a bigger box
                        $newLighterBoxPacker->setBoxes($this->boxes);
                        $newLighterBoxPacker->setItems($newItemsForLighterBox);
                        $this->logger->log(LogLevel::INFO, '[ATTEMPTING TO PACK LIGHTER BOX]');
                        $newLighterBox = $newLighterBoxPacker->doVolumePacking()->top();

                        if ($newLighterBox->getItems()->count() === count($newItemsForLighterBox)) { //new item fits
                            $this->logger->log(LogLevel::DEBUG, 'New item fits');
                            unset($overWeightBoxItems[$oi]); //now packed in different box

                            if (count($overWeightBoxItems) > 0) {
                                $newHeavierBoxPacker = new Packer(); //we may be able to use a smaller box
                                $newHeavierBoxPacker->setBoxes($this->boxes);
                                $newHeavierBoxPacker->setItems($overWeightBoxItems);

                                $this->logger->log(LogLevel::INFO, '[ATTEMPTING TO PACK HEAVIER BOX]');
                                $newHeavierBoxes = $newHeavierBoxPacker->doVolumePacking();
                                if ($newHeavierBoxes->count()
                                    > 1) { //found an edge case in packing algorithm that *increased* box count
                                    $this->logger->log(
                                        LogLevel::INFO,
                                        '[REDISTRIBUTING WEIGHT] Abandoning redistribution, because new packing is less efficient than original'
                                    );

                                    return $originalBoxes;
                                }

                                $this->overWeightBoxes[$o] = $newHeavierBoxes->top();
                            } else {
                                unset($this->overWeightBoxes[$o]);
                            }
                            $this->underWeightBoxes[$u] = $newLighterBox;

                            $tryRepack = true; //we did some work, so see if we can do even better
                            break 3;
                        }
                    }
                }
            }
        } while ($tryRepack);

        //Combine back into a single list
        $newPackedBoxes = new PackedBoxList();
        $newPackedBoxes->insertFromArray($this->overWeightBoxes);
        $newPackedBoxes->insertFromArray($this->targetWeightBoxes);
        $newPackedBoxes->insertFromArray($this->underWeightBoxes);

        return $newPackedBoxes;
    }

    /**
     * Classify boxes into under/target/overweight.
     *
     * @param PackedBoxList $boxes
     * @param float $targetWeight
     */
    protected function classifyBoxes(PackedBoxList $boxes, float $targetWeight): void
    {
        // reset any previous working state
        $this->underWeightBoxes = [];
        $this->targetWeightBoxes = [];
        $this->overWeightBoxes = [];

        /** @var PackedBox $packedBox */
        foreach ($boxes as $packedBox) {
            $boxWeight = $packedBox->getWeight();
            if ($boxWeight > $targetWeight) {
                $this->overWeightBoxes[] = $packedBox;
            } elseif ($boxWeight < $targetWeight) {
                $this->underWeightBoxes[] = $packedBox;
            } else {
                $this->targetWeightBoxes[] = $packedBox;
            }
        }
    }

    /**
     * @param PackedBox $boxA
     * @param PackedBox $boxB
     *
     * @return int
     */
    protected function sortMoreSpaceFirst(PackedBox $boxA, PackedBox $boxB): int
    {
        $choice = $boxB->getItems()->count() - $boxA->getItems()->count();
        if ($choice === 0) {
            $choice = $boxA->getInnerVolume() - $boxB->getInnerVolume();
        }
        if ($choice === 0) {
            $choice = $boxB->getWeight() - $boxA->getWeight();
        }

        return $choice;
    }
}
