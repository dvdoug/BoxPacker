<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

/**
 * An item to be packed where additional constraints need to be considered. Only implement this interface if you actually
 * need this additional functionality as it will slow down the packing algorithm.
 *
 * @author Doug Wright
 */
interface ConstrainedPlacementItem extends Item
{
    /**
     * Hook for user implementation of item-specific constraints, e.g. max <x> batteries per box.
     */
    public function canBePacked(
        Box $box,
        PackedItemList $alreadyPackedItems,
        int $proposedX,
        int $proposedY,
        int $proposedZ,
        int $width,
        int $length,
        int $depth
    ): bool;
}
