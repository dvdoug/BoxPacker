<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use function count;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use SplObjectStorage;
use function usort;

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
     * Quantities available of each box type.
     *
     * @var SplObjectStorage
     */
    protected $boxesQtyAvailable;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->items = new ItemList();
        $this->boxes = new BoxList();
        $this->boxesQtyAvailable = new SplObjectStorage();

        $this->logger = new NullLogger();
    }

    /**
     * Add item to be packed.
     */
    public function addItem(Item $item, int $qty = 1): void
    {
        for ($i = 0; $i < $qty; ++$i) {
            $this->items->insert($item);
        }
        $this->logger->log(LogLevel::INFO, "added {$qty} x {$item->getDescription()}", ['item' => $item]);
    }

    /**
     * Set a list of items all at once.
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
        $this->boxesQtyAvailable[$box] = $qty;
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

    /**
     * Pack items into boxes.
     */
    public function pack(): PackedBoxList
    {
        $packedBoxes = $this->doVolumePacking();

        //If we have multiple boxes, try and optimise/even-out weight distribution
        if ($packedBoxes->count() > 1 && $packedBoxes->count() <= $this->maxBoxesToBalanceWeight) {
            $redistributor = new WeightRedistributor($this->boxes, $this->boxesQtyAvailable);
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
    public function doVolumePacking(): PackedBoxList
    {
        $packedBoxes = new PackedBoxList();

        $this->sanityPrecheck();

        //Keep going until everything packed
        while ($this->items->count()) {
            $packedBoxesIteration = [];

            //Loop through boxes starting with smallest, see what happens
            foreach ($this->boxes as $box) {
                if ($this->boxesQtyAvailable[$box] > 0) {
                    $volumePacker = new VolumePacker($box, clone $this->items);
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
            }

            //Find best box of iteration, and remove packed items from unpacked list
            $bestBox = $this->findBestBoxFromIteration($packedBoxesIteration);

            /** @var PackedItem $packedItem */
            foreach ($bestBox->getItems() as $packedItem) {
                $this->items->remove($packedItem->getItem());
            }

            $packedBoxes->insert($bestBox);
            $this->boxesQtyAvailable[$bestBox->getBox()] = $this->boxesQtyAvailable[$bestBox->getBox()] - 1;
        }

        return $packedBoxes;
    }

    /**
     * @param PackedBox[] $packedBoxes
     */
    protected function findBestBoxFromIteration(array $packedBoxes): PackedBox
    {
        if (count($packedBoxes) === 0) {
            throw new NoBoxesAvailableException("No boxes could be found for item '{$this->items->top()->getDescription()}'", $this->items->top());
        }

        usort($packedBoxes, [$this, 'compare']);

        return $packedBoxes[0];
    }

    private function sanityPrecheck(): void
    {
        /** @var Item $item */
        foreach ($this->items as $item) {
            $possibleFits = 0;

            /** @var Box $box */
            foreach ($this->boxes as $box) {
                if ($item->getWeight() <= ($box->getMaxWeight() - $box->getEmptyWeight())) {
                    $possibleFits += count((new OrientatedItemFactory($box))->getPossibleOrientationsInEmptyBox($item));
                }
            }

            if ($possibleFits === 0) {
                throw new ItemTooLargeException("Item '{$item->getDescription()}' is too large to fit into any box", $item);
            }
        }
    }

    private static function compare(PackedBox $boxA, PackedBox $boxB): int
    {
        $choice = $boxB->getItems()->count() <=> $boxA->getItems()->count();

        if ($choice === 0) {
            $choice = $boxB->getVolumeUtilisation() <=> $boxA->getVolumeUtilisation();
        }
        if ($choice === 0) {
            $choice = $boxB->getUsedVolume() <=> $boxA->getUsedVolume();
        }

        return $choice;
    }
}
