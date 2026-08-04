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
        $spaces = [new VoidSpace(0, 0, 0, $boxWidth, $boxLength, $boxDepth)];

        foreach ($packedItems as $packedItem) {
            $next = [];
            foreach ($spaces as $space) {
                foreach ($this->subtractItemFromSpace($space, $packedItem) as $residual) {
                    $next[] = $residual;
                }
            }
            $spaces = $next;
        }

        return $spaces;
    }

    /**
     * Split a free space by removing the part occupied by a packed item.
     *
     * @return VoidSpace[] remaining free pieces (or the original space if the item misses it)
     */
    private function subtractItemFromSpace(VoidSpace $space, PackedItem $item): array
    {
        $spaceX = $space->x;
        $spaceY = $space->y;
        $spaceZ = $space->z;
        $spaceEndX = $space->x + $space->width;
        $spaceEndY = $space->y + $space->length;
        $spaceEndZ = $space->z + $space->depth;

        $itemX = $item->x;
        $itemY = $item->y;
        $itemZ = $item->z;
        $itemEndX = $item->x + $item->width;
        $itemEndY = $item->y + $item->length;
        $itemEndZ = $item->z + $item->depth;

        // Item does not meet this free space — leave it alone
        if ($itemX >= $spaceEndX || $itemEndX <= $spaceX
            || $itemY >= $spaceEndY || $itemEndY <= $spaceY
            || $itemZ >= $spaceEndZ || $itemEndZ <= $spaceZ) {
            return [$space];
        }

        // Part of the item that sits inside this free space
        $overlapX = max($spaceX, $itemX);
        $overlapY = max($spaceY, $itemY);
        $overlapZ = max($spaceZ, $itemZ);
        $overlapEndX = min($spaceEndX, $itemEndX);
        $overlapEndY = min($spaceEndY, $itemEndY);
        $overlapEndZ = min($spaceEndZ, $itemEndZ);

        // Up to six leftover pieces around that overlap (no gaps, no double-counting)
        $residuals = [];

        // Left (full free length and depth)
        if ($overlapX > $spaceX) {
            $residuals[] = new VoidSpace($spaceX, $spaceY, $spaceZ, $overlapX - $spaceX, $space->length, $space->depth);
        }

        // Right (full free length and depth)
        if ($overlapEndX < $spaceEndX) {
            $residuals[] = new VoidSpace($overlapEndX, $spaceY, $spaceZ, $spaceEndX - $overlapEndX, $space->length, $space->depth);
        }

        // Front — only across the overlap width (full free depth)
        if ($overlapY > $spaceY) {
            $residuals[] = new VoidSpace($overlapX, $spaceY, $spaceZ, $overlapEndX - $overlapX, $overlapY - $spaceY, $space->depth);
        }

        // Back
        if ($overlapEndY < $spaceEndY) {
            $residuals[] = new VoidSpace($overlapX, $overlapEndY, $spaceZ, $overlapEndX - $overlapX, $spaceEndY - $overlapEndY, $space->depth);
        }

        // Below — only across the overlap footprint
        if ($overlapZ > $spaceZ) {
            $residuals[] = new VoidSpace($overlapX, $overlapY, $spaceZ, $overlapEndX - $overlapX, $overlapEndY - $overlapY, $overlapZ - $spaceZ);
        }

        // Above
        if ($overlapEndZ < $spaceEndZ) {
            $residuals[] = new VoidSpace($overlapX, $overlapY, $overlapEndZ, $overlapEndX - $overlapX, $overlapEndY - $overlapY, $spaceEndZ - $overlapEndZ);
        }

        return $residuals;
    }
}
