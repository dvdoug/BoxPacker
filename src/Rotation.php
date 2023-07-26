<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

/*
 * Rotation permutations
 */
class Rotation
{
    /* Can be turned sideways 90°, but cannot be placed *on* it's side e.g. fragile "↑this way up" items */
    public const KeepFlat = 2;
    /* No handling restrictions, item can be placed in any orientation */
    public const BestFit = 6;
}
