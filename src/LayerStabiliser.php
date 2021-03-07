<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use function usort;

/**
 * Applies load stability to generated result.
 *
 * @author Doug Wright
 * @internal
 */
class LayerStabiliser
{
    /**
     * @param PackedLayer[] $packedLayers
     *
     * @return PackedLayer[]
     */
    public function stabilise(array $packedLayers): array
    {
        // first re-order according to footprint
        $stabilisedLayers = [];
        usort($packedLayers, [$this, 'compare']);

        // then for each item in the layer, re-calculate each item's z position
        $currentZ = 0;
        foreach ($packedLayers as $oldZLayer) {
            $oldZStart = $oldZLayer->getStartZ();
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

    private function compare(PackedLayer $layerA, PackedLayer $layerB): int
    {
        return ($layerB->getFootprint() <=> $layerA->getFootprint()) ?: ($layerB->getDepth() <=> $layerA->getDepth());
    }
}
