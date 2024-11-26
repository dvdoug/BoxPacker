<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker\Test;

use DVDoug\BoxPacker\ConstrainedPlacementItem;
use DVDoug\BoxPacker\PackedBox;
use DVDoug\BoxPacker\PackedItem;

use function array_filter;
use function iterator_to_array;

class ConstrainedPlacementNoStackingTestItem extends TestItem implements ConstrainedPlacementItem
{
    /**
     * Hook for user implementation of item-specific constraints, e.g. max <x> batteries per box.
     */
    public function canBePacked(
        PackedBox $packedBox,
        int $proposedX,
        int $proposedY,
        int $proposedZ,
        int $width,
        int $length,
        int $depth
    ): bool {
        $alreadyPackedType = array_filter(
            iterator_to_array($packedBox->items, false),
            fn (PackedItem $item) => $item->item->getDescription() === $this->getDescription()
        );

        /** @var PackedItem $alreadyPacked */
        foreach ($alreadyPackedType as $alreadyPacked) {
            if (
                $alreadyPacked->z + $alreadyPacked->depth === $proposedZ
                && $proposedX >= $alreadyPacked->x && $proposedX <= ($alreadyPacked->x + $alreadyPacked->width)
                && $proposedY >= $alreadyPacked->y && $proposedY <= ($alreadyPacked->y + $alreadyPacked->length)) {
                return false;
            }
        }

        return true;
    }
}
