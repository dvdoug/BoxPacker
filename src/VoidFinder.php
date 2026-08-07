<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use function max;
use function min;

/**
 * Finds empty rectangular regions inside a box given already-packed items.
 *
 * Starts from the full working volume, then for each packed item replaces every free region
 * that intersects it with up to six leftover rectangular pieces (left, right, front,
 * back, below, above). The result includes both exterior leftovers and internal voids.
 *
 * Dimensions are the working width/length/depth used by layer packing (width and length may
 * be swapped when the box is evaluated rotated in plan view — not always the box's nominal
 * inner width/length).
 *
 * @internal
 */
class VoidFinder
{
    /**
     * Find free rectangular spaces in a working volume of the given size.
     *
     * @param  iterable<PackedItem> $packedItems already placed in the same coordinate frame, non-overlapping
     * @return VoidSpace[]
     */
    public function find(int $boxWidth, int $boxLength, int $boxDepth, iterable $packedItems): array
    {
        return $this->subtractItems(
            [new VoidSpace(0, 0, 0, $boxWidth, $boxLength, $boxDepth)],
            $packedItems
        );
    }

    public function subtractItems(array $spaces, iterable $packedItems): array
    {
        foreach ($packedItems as $packedItem) {
            // Hoist item AABB once per packed item (not once per free space)
            $itemX = $packedItem->x;
            $itemY = $packedItem->y;
            $itemZ = $packedItem->z;
            $itemEndX = $itemX + $packedItem->width;
            $itemEndY = $itemY + $packedItem->length;
            $itemEndZ = $itemZ + $packedItem->depth;

            $next = [];
            foreach ($spaces as $space) {
                $spaceX = $space->x;
                $spaceY = $space->y;
                $spaceZ = $space->z;
                $spaceEndX = $spaceX + $space->width;
                $spaceEndY = $spaceY + $space->length;
                $spaceEndZ = $spaceZ + $space->depth;

                // No overlap — keep the free space as-is
                if ($itemX >= $spaceEndX || $itemEndX <= $spaceX
                    || $itemY >= $spaceEndY || $itemEndY <= $spaceY
                    || $itemZ >= $spaceEndZ || $itemEndZ <= $spaceZ) {
                    $next[] = $space;
                    continue;
                }

                // Part of the item that sits inside this free space
                $overlapX = max($spaceX, $itemX);
                $overlapY = max($spaceY, $itemY);
                $overlapZ = max($spaceZ, $itemZ);
                $overlapEndX = min($spaceEndX, $itemEndX);
                $overlapEndY = min($spaceEndY, $itemEndY);
                $overlapEndZ = min($spaceEndZ, $itemEndZ);

                $spaceLength = $space->length;
                $spaceDepth = $space->depth;
                $overlapWidth = $overlapEndX - $overlapX;
                $overlapLength = $overlapEndY - $overlapY;

                // Residuals around the overlap (no gaps, no double-counting)
                if ($overlapX > $spaceX) { // left
                    $next[] = new VoidSpace($spaceX, $spaceY, $spaceZ, $overlapX - $spaceX, $spaceLength, $spaceDepth);
                }
                if ($overlapEndX < $spaceEndX) { // right
                    $next[] = new VoidSpace($overlapEndX, $spaceY, $spaceZ, $spaceEndX - $overlapEndX, $spaceLength, $spaceDepth);
                }
                if ($overlapY > $spaceY) { // front
                    $next[] = new VoidSpace($overlapX, $spaceY, $spaceZ, $overlapWidth, $overlapY - $spaceY, $spaceDepth);
                }
                if ($overlapEndY < $spaceEndY) { // back
                    $next[] = new VoidSpace($overlapX, $overlapEndY, $spaceZ, $overlapWidth, $spaceEndY - $overlapEndY, $spaceDepth);
                }
                if ($overlapZ > $spaceZ) { // below
                    $next[] = new VoidSpace($overlapX, $overlapY, $spaceZ, $overlapWidth, $overlapLength, $overlapZ - $spaceZ);
                }
                if ($overlapEndZ < $spaceEndZ) { // above
                    $next[] = new VoidSpace($overlapX, $overlapY, $overlapEndZ, $overlapWidth, $overlapLength, $spaceEndZ - $overlapEndZ);
                }
            }
            $spaces = $next;
        }

        return $spaces;
    }
}
