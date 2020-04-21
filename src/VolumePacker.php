<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
namespace DVDoug\BoxPacker;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Actual packer.
 *
 * @author Doug Wright
 */
class VolumePacker implements LoggerAwareInterface
{
    /**
     * The logger instance.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Box to pack items into.
     *
     * @var Box
     */
    protected $box;

    /**
     * List of items to be packed.
     *
     * @var ItemList
     */
    protected $items;

    /**
     * Whether the packer is in single-pass mode.
     *
     * @var bool
     */
    protected $singlePassMode = false;

    /**
     * @var LayerPacker
     */
    private $layerPacker;

    /**
     * @var bool
     */
    private $hasConstrainedItems;

    /**
     * Constructor.
     */
    public function __construct(Box $box, ItemList $items)
    {
        $this->box = $box;
        $this->items = clone $items;

        $this->logger = new NullLogger();

        $this->hasConstrainedItems = $items->hasConstrainedItems();

        $this->layerPacker = new LayerPacker($this->box);
        $this->layerPacker->setLogger($this->logger);
    }

    /**
     * Sets a logger.
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->layerPacker->setLogger($logger);
    }

    /**
     * @internal
     */
    public function setSinglePassMode($singlePassMode)
    {
        $this->singlePassMode = $singlePassMode;
        $this->layerPacker->setSinglePassMode($singlePassMode);
    }

    /**
     * Pack as many items as possible into specific given box.
     *
     * @return PackedBox packed box
     */
    public function pack()
    {
        $this->logger->debug("[EVALUATING BOX] {$this->box->getReference()}", ['box' => $this->box]);

        $rotationsToTest = [false];
        if (!$this->singlePassMode) {
            $rotationsToTest[] = true;
        }

        $boxPermutations = [];
        foreach ($rotationsToTest as $rotation) {
            if ($rotation) {
                $boxWidth = $this->box->getInnerLength();
                $boxLength = $this->box->getInnerWidth();
            } else {
                $boxWidth = $this->box->getInnerWidth();
                $boxLength = $this->box->getInnerLength();
            }

            $boxPermutation = $this->packRotation($boxWidth, $boxLength);
            if ($boxPermutation->getItems()->count() === $this->items->count()) {
                return $boxPermutation;
            }

            $boxPermutations[] = $boxPermutation;
        }

        usort($boxPermutations, static function (PackedBox $a, PackedBox $b) {
            if ($a->getVolumeUtilisation() < $b->getVolumeUtilisation()) {
                return 1;
            }
            return -1;
        });

        return reset($boxPermutations);
    }

    /**
     * Pack as many items as possible into specific given box.
     *
     * @return PackedBox packed box
     */
    private function packRotation($boxWidth, $boxLength)
    {
        $this->logger->debug("[EVALUATING ROTATION] {$this->box->getReference()}", ['width' => $boxWidth, 'length' => $boxLength]);

        /** @var PackedLayer[] $layers */
        $layers = [];
        $items = clone $this->items;

        while ($items->count() > 0) {
            $layerStartDepth = static::getCurrentPackedDepth($layers);
            $packedItemList = $this->getPackedItemList($layers);

            //do a preliminary layer pack to get the depth used
            $preliminaryItems = clone $items;
            $preliminaryLayer = $this->layerPacker->packLayer($preliminaryItems, clone $packedItemList, $layers, $layerStartDepth, $boxWidth, $boxLength, $this->box->getInnerDepth() - $layerStartDepth, 0);
            if (count($preliminaryLayer->getItems()) === 0) {
                break;
            }

            if ($preliminaryLayer->getDepth() === $preliminaryLayer->getItems()[0]->getDepth()) { // preliminary === final
                $layers[] = $preliminaryLayer;
                $items = $preliminaryItems;
            } else { //redo with now-known-depth so that we can stack to that height from the first item
                $layers[] = $this->layerPacker->packLayer($items, $packedItemList, $layers, $layerStartDepth, $boxWidth, $boxLength, $this->box->getInnerDepth() - $layerStartDepth, $preliminaryLayer->getDepth());
            }
        }

        $layers = $this->correctLayerRotation($layers, $boxWidth);
        $layers = $this->stabiliseLayers($layers);

        return PackedBox::fromPackedItemList($this->box, $this->getPackedItemList($layers));
    }

    /**
     * During packing, it is quite possible that layers have been created that aren't physically stable
     * i.e. they overhang the ones below.
     *
     * This function reorders them so that the ones with the greatest surface area are placed at the bottom
     *
     * @param  PackedLayer[] $oldLayers
     * @return PackedLayer[]
     */
    private function stabiliseLayers(array $oldLayers)
    {
        if ($this->singlePassMode || $this->hasConstrainedItems) { // constraints include position, so cannot change
            return $oldLayers;
        }

        $stabiliser = new LayerStabiliser();

        return $stabiliser->stabilise($oldLayers);
    }

    /**
     * Swap back width/length of the packed items to match orientation of the box if needed.
     *
     * @param PackedLayer[] $oldLayers
     */
    private function correctLayerRotation(array $oldLayers, $boxWidth)
    {
        if ($this->box->getInnerWidth() === $boxWidth) {
            return $oldLayers;
        }

        $newLayers = [];
        foreach ($oldLayers as $originalLayer) {
            $newLayer = new PackedLayer();
            foreach ($originalLayer->getItems() as $item) {
                $packedItem = new PackedItem($item->getItem(), $item->getY(), $item->getX(), $item->getZ(), $item->getLength(), $item->getWidth(), $item->getDepth());
                $newLayer->insert($packedItem);
            }
            $newLayers[] = $newLayer;
        }

        return $newLayers;
    }

    /**
     * Generate a single list of items packed.
     * @param PackedLayer[] $layers
     */
    private function getPackedItemList(array $layers)
    {
        $packedItemList = new PackedItemList();
        foreach ($layers as $layer) {
            foreach ($layer->getItems() as $packedItem) {
                $packedItemList->insert($packedItem);
            }
        }

        return $packedItemList;
    }

    /**
     * Return the current packed depth.
     *
     * @param PackedLayer[] $layers
     */
    private static function getCurrentPackedDepth(array $layers)
    {
        $depth = 0;
        foreach ($layers as $layer) {
            $depth += $layer->getDepth();
        }

        return $depth;
    }
}
