<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

/**
 * Class ItemTooLargeException
 * Exception used when an item is too large to pack into any box.
 * @deprecated now unused, just catch NoBoxesAvailableException
 */
class ItemTooLargeException extends NoBoxesAvailableException
{
}
