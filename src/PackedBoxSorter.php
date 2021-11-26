<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

/**
 * A callback to be used with usort(), implementing logic to determine which PackedBox is "better".
 */
interface PackedBoxSorter
{
    /**
     * Return -1 if $boxA is "best", 1 if $boxB is "best" or 0 if neither is "best".
     */
    public function compare(PackedBox $boxA, PackedBox $boxB): int;
}
