<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use RuntimeException;

/**
 * Class ItemTooLargeException
 * Exception used when an item is too large to pack.
 */
class IncreasedBoxCountException extends RuntimeException
{
}
