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
    protected $boxList;

    /**
     * @var PackedBoxList
     */
    protected $originalPackedBoxes;

    /**
     * @var PackedBox[]
     */
    protected $underWeightBoxes = [];

    /**
     * @var PackedBox[]
     */
    protected $targetWeightBoxes = [];

    /**
     * @var PackedBox[]
     */
    protected $overWeightBoxes = [];

    /**
     * @var float
     */
    protected $targetWeight;

    /**
     * Constructor
     *
     * @param BoxList       $boxList
     * @param PackedBoxList $originalPackedBoxes
     */
    public function __construct(BoxList $boxList, PackedBoxList $originalPackedBoxes)
    {
        $this->boxList = clone $boxList;
        $this->originalPackedBoxes = $originalPackedBoxes;
        $this->logger = new NullLogger();

        $this->targetWeight = $this->originalPackedBoxes->getMeanWeight();
        $this->sortBoxes();
    }

    /**
     * Given a solution set of packed boxes, repack them to achieve optimum weight distribution
     *
     * @return PackedBoxList
     */
    public function redistributeWeight()
    {
        $this->logger->log(LogLevel::DEBUG, "repacking for weight distribution, weight variance {$this->originalPackedBoxes->getWeightVariance()}, target weight {$this->targetWeight}");

        $packedBoxes = new PackedBoxList;

        do { //Keep moving items from most overweight box to most underweight box
            $tryRepack = false;
            $this->logger->log(LogLevel::DEBUG, 'boxes under/over target: '.count($this->underWeightBoxes).'/'.count($this->overWeightBoxes));

            foreach ($this->underWeightBoxes as $u => $underWeightBox) {
                $this->logger->log(LogLevel::DEBUG, 'Underweight Box '.$u);
                foreach ($this->overWeightBoxes as $o => $overWeightBox) {
                    $this->logger->log(LogLevel::DEBUG, 'Overweight Box '.$o);
                    $overWeightBoxItems = $overWeightBox->getItems()->asArray();

                    //For each item in the heavier box, try and move it to the lighter one
                    foreach ($overWeightBoxItems as $oi => $overWeightBoxItem) {
                        $this->logger->log(LogLevel::DEBUG, 'Overweight Item '.$oi);
                        if ($underWeightBox->getWeight() + $overWeightBoxItem->getWeight() > $this->targetWeight) {
                            $this->logger->log(LogLevel::DEBUG, 'Skipping item for hindering weight distribution');
                            continue; //skip if moving this item would hinder rather than help weight distribution
                        }

                        $newItemsForLighterBox = clone $underWeightBox->getItems();
                        $newItemsForLighterBox->insert($overWeightBoxItem);

                        $newLighterBoxPacker = new Packer(); //we may need a bigger box
                        $newLighterBoxPacker->setBoxes($this->boxList);
                        $newLighterBoxPacker->setItems($newItemsForLighterBox);
                        $this->logger->log(LogLevel::INFO, "[ATTEMPTING TO PACK LIGHTER BOX]");
                        $newLighterBox = $newLighterBoxPacker->doVolumePacking()->getIterator()->current();

                        if ($newLighterBox->getItems()->count() === $newItemsForLighterBox->count()) { //new item fits
                            $this->logger->log(LogLevel::DEBUG, 'New item fits');
                            unset($overWeightBoxItems[$oi]); //now packed in different box

                            $newHeavierBoxPacker = new Packer(); //we may be able to use a smaller box
                            $newHeavierBoxPacker->setBoxes($this->boxList);
                            $newHeavierBoxPacker->setItems($overWeightBoxItems);

                            $this->logger->log(LogLevel::INFO, "[ATTEMPTING TO PACK HEAVIER BOX]");
                            $newHeavierBoxes = $newHeavierBoxPacker->doVolumePacking();
                            if (count($newHeavierBoxes) > 1) { //found an edge case in packing algorithm that *increased* box count
                                $this->logger->log(LogLevel::INFO, "[REDISTRIBUTING WEIGHT] Abandoning redistribution, because new packing is less efficient than original");
                                return $this->originalPackedBoxes;
                            }

                            $this->overWeightBoxes[$o] = $newHeavierBoxes->getIterator()->current();
                            $this->underWeightBoxes[$u] = $newLighterBox;

                            $tryRepack = true; //we did some work, so see if we can do even better
                            usort($this->overWeightBoxes, [$packedBoxes, 'reverseCompare']);
                            usort($this->underWeightBoxes, [$packedBoxes, 'reverseCompare']);
                            break 3;
                        }
                    }
                }
            }
        } while ($tryRepack);

        //Combine back into a single list
        $packedBoxes->insertFromArray($this->overWeightBoxes);
        $packedBoxes->insertFromArray($this->underWeightBoxes);
        $packedBoxes->insertFromArray($this->targetWeightBoxes);

        return $packedBoxes;
    }

    /**
     * Perform initial classification of boxes into under/over/target weight
     */
    protected function sortBoxes() {
        foreach (clone $this->originalPackedBoxes as $packedBox) {
            $boxWeight = $packedBox->getWeight();
            if ($boxWeight > $this->targetWeight) {
                $this->overWeightBoxes[] = $packedBox;
            } elseif ($boxWeight < $this->targetWeight) {
                $this->underWeightBoxes[] = $packedBox;
            } else {
                $this->targetWeightBoxes[] = $packedBox; //target weight, so we'll keep these
            }
        }
    }
}
