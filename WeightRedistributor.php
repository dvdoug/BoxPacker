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
class WeightRedistributor implements LoggerAwareInterface
{

    use LoggerAwareTrait;

    /**
     * List of box sizes available to pack items into
     * @var BoxList
     */
    protected $boxes;

    /**
     * Constructor
     */
    public function __construct(BoxList $boxList)
    {
        $this->boxes = clone $boxList;
        $this->logger = new NullLogger();
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
}
