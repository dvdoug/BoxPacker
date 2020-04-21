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
class Packer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Number of boxes at which balancing weight is deemed not worth it.
     *
     * @var int
     */
    protected $maxBoxesToBalanceWeight = 12;

    /**
     * List of items to be packed.
     *
     * @var ItemList
     */
    protected $items;

    /**
     * List of box sizes available to pack items into.
     *
     * @var BoxList
     */
    protected $boxes;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->items = new ItemList();
        $this->boxes = new BoxList();

        $this->logger = new NullLogger();
    }

    /**
     * Add item to be packed.
     *
     * @param Item $item
     * @param int  $qty
     */
    public function addItem(Item $item, $qty = 1)
    {
        for ($i = 0; $i < $qty; ++$i) {
            $this->items->insert($item);
        }
        $this->logger->log(LogLevel::INFO, "added {$qty} x {$item->getDescription()}", ['item' => $item]);
    }

    /**
     * Set a list of items all at once.
     *
     * @param iterable|Item[] $items
     */
    public function setItems($items)
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
     *
     * @param Box $box
     */
    public function addBox(Box $box)
    {
        $this->boxes->insert($box);
        $this->logger->log(LogLevel::INFO, "added box {$box->getReference()}", ['box' => $box]);
    }

    /**
     * Add a pre-prepared set of boxes all at once.
     *
     * @param BoxList $boxList
     */
    public function setBoxes(BoxList $boxList)
    {
        $this->boxes = clone $boxList;
    }

    /**
     * Number of boxes at which balancing weight is deemed not worth the extra computation time.
     *
     * @return int
     */
    public function getMaxBoxesToBalanceWeight()
    {
        return $this->maxBoxesToBalanceWeight;
    }

    /**
     * Number of boxes at which balancing weight is deemed not worth the extra computation time.
     *
     * @param int $maxBoxesToBalanceWeight
     */
    public function setMaxBoxesToBalanceWeight($maxBoxesToBalanceWeight)
    {
        $this->maxBoxesToBalanceWeight = $maxBoxesToBalanceWeight;
    }

    /**
     * Pack items into boxes.
     *
     * @return PackedBoxList
     */
    public function pack()
    {
        $this->sanityPrecheck();
        $packedBoxes = $this->doVolumePacking();

        //If we have multiple boxes, try and optimise/even-out weight distribution
        if ($packedBoxes->count() > 1 && $packedBoxes->count() <= $this->maxBoxesToBalanceWeight) {
            $redistributor = new WeightRedistributor($this->boxes);
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
     *
     * @return PackedBoxList
     */
    public function doVolumePacking($singlePassMode = false, $enforceSingleBox = false)
    {
        $packedBoxes = new PackedBoxList();

        //Keep going until everything packed
        while ($this->items->count()) {
            $packedBoxesIteration = [];

            //Loop through boxes starting with smallest, see what happens
            foreach ($this->getBoxList($enforceSingleBox) as $box) {
                $volumePacker = new VolumePacker($box, $this->items);
                $volumePacker->setLogger($this->logger);
                $volumePacker->setSinglePassMode($singlePassMode);
                $packedBox = $volumePacker->pack();
                if ($packedBox->getItems()->count()) {
                    $packedBoxesIteration[] = $packedBox;

                    //Have we found a single box that contains everything?
                    if ($packedBox->getItems()->count() === $this->items->count()) {
                        break;
                    }
                }
            }

            try {
                //Find best box of iteration, and remove packed items from unpacked list
                $bestBox = $this->findBestBoxFromIteration($packedBoxesIteration);
            } catch (NoBoxesAvailableException $e) {
                if ($enforceSingleBox) {
                    return new PackedBoxList();
                }
                throw $e;
            }

            $this->items->removePackedItems($bestBox->getPackedItems());

            $packedBoxes->insert($bestBox);
        }

        return $packedBoxes;
    }

    /**
     * Get a "smart" ordering of the boxes to try packing items into. The initial BoxList is already sorted in order
     * so that the smallest boxes are evaluated first, but this means that time is spent on boxes that cannot possibly
     * hold the entire set of items due to volume limitations. These should be evaluated first.
     */
    protected function getBoxList($enforceSingleBox = false)
    {
        $itemVolume = 0;
        foreach (clone $this->items as $item) {
            $itemVolume += $item->getWidth() * $item->getLength() * $item->getDepth();
        }

        $preferredBoxes = [];
        $otherBoxes = [];
        foreach (clone $this->boxes as $box) {
            if ($box->getInnerWidth() * $box->getInnerLength() * $box->getInnerDepth() >= $itemVolume) {
                $preferredBoxes[] = $box;
            } elseif (!$enforceSingleBox) {
                $otherBoxes[] = $box;
            }
        }

        return array_merge($preferredBoxes, $otherBoxes);
    }

    /**
     * @param PackedBox[] $packedBoxes
     */
    protected function findBestBoxFromIteration(array $packedBoxes)
    {
        if (count($packedBoxes) === 0) {
            throw new NoBoxesAvailableException("No boxes could be found for item '{$this->items->top()->getDescription()}'", $this->items->top());
        }

        usort($packedBoxes, [$this, 'compare']);

        return $packedBoxes[0];
    }

    private function sanityPrecheck()
    {
        /** @var Item $item */
        foreach (clone $this->items as $item) {
            $possibleFits = 0;

            /** @var Box $box */
            foreach (clone $this->boxes as $box) {
                if ($item->getWeight() <= ($box->getMaxWeight() - $box->getEmptyWeight())) {
                    $possibleFits += count((new OrientatedItemFactory($box))->getPossibleOrientationsInEmptyBox($item));
                }
            }

            if ($possibleFits === 0) {
                throw new ItemTooLargeException("Item '{$item->getDescription()}' is too large to fit into any box", $item);
            }
        }
    }

    private static function compare(PackedBox $boxA, PackedBox $boxB)
    {
        $choice = $boxB->getItems()->count() - $boxA->getItems()->count();

        if ($choice == 0) {
            $choice = $boxB->getVolumeUtilisation() - $boxA->getVolumeUtilisation();
        }
        if ($choice == 0) {
            $choice = $boxB->getUsedVolume() - $boxA->getUsedVolume();
        }
        if ($choice == 0) {
            $choice = PHP_MAJOR_VERSION > 5 ? -1 : 1;
        }

        return $choice;
    }
}
