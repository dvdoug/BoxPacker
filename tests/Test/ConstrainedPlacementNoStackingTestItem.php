<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker\Test;

use DVDoug\BoxPacker\Box;
use DVDoug\BoxPacker\ConstrainedPlacementItem;
use DVDoug\BoxPacker\PackedItem;
use DVDoug\BoxPacker\PackedItemList;
use function iterator_to_array;

class ConstrainedPlacementNoStackingTestItem extends TestItem implements ConstrainedPlacementItem
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
    ): bool {
        $alreadyPackedType = array_filter(
            iterator_to_array($alreadyPackedItems, false),
            function (PackedItem $item) {
                return $item->getItem()->getDescription() === $this->getDescription();
            }
        );

        /** @var PackedItem $alreadyPacked */
        foreach ($alreadyPackedType as $alreadyPacked) {
            if (
                $alreadyPacked->getZ() + $alreadyPacked->getDepth() === $proposedZ &&
                $proposedX >= $alreadyPacked->getX() && $proposedX <= ($alreadyPacked->getX() + $alreadyPacked->getWidth()) &&
                $proposedY >= $alreadyPacked->getY() && $proposedY <= ($alreadyPacked->getY() + $alreadyPacked->getLength())) {
                return false;
            }
        }

        return true;
    }
}
