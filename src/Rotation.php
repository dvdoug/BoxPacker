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
enum Rotation: int
{
    /* Must be placed in it's defined orientation only */
    case Never = 1;
    /* Can be turned sideways 90°, but cannot be placed *on* it's side e.g. fragile "↑this way up" items */
    case KeepFlat = 2;
    /* No handling restrictions, item can be placed in any orientation */
    case BestFit = 6;
}
