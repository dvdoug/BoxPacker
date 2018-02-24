<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Applies load stability to generated result.
 *
 * @author Doug Wright
 */
class LayerStabiliser implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * @param PackedLayer[] $packedLayers
     *
     * @return PackedLayer[]
     */
    public function stabilise(array $packedLayers)
    {
        // first re-order according to footprint
        $stabilisedLayers = [];
        usort($packedLayers, [$this, 'compare']);

        // then for each item in the layer, re-calculate each item's z position
        $currentZ = 0;
        foreach ($packedLayers as $oldZLayer) {
            $oldZStart = $oldZLayer->getStartDepth();
            $newZLayer = new PackedLayer();
            foreach ($oldZLayer->getItems() as $oldZItem) {
                $newZ = $oldZItem->getZ() - $oldZStart + $currentZ;
                $newZItem = new PackedItem($oldZItem->getItem(), $oldZItem->getX(), $oldZItem->getY(), $newZ, $oldZItem->getWidth(), $oldZItem->getLength(), $oldZItem->getDepth());
                $newZLayer->insert($newZItem);
            }

            $stabilisedLayers[] = $newZLayer;
            $currentZ += $newZLayer->getDepth();
        }

        return $stabilisedLayers;
    }

    /**
     * @param PackedLayer $layerA
     * @param PackedLayer $layerB
     *
     * @return int
     */
    private function compare(PackedLayer $layerA, PackedLayer $layerB)
    {
        return ($layerB->getFootprint() - $layerA->getFootprint()) ?: ($layerB->getDepth() - $layerA->getDepth());
    }
}
