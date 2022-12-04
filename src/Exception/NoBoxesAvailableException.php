<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker\Exception;

use DVDoug\BoxPacker\ItemList;
use RuntimeException;

/**
 * Exception used when an item cannot be packed into any box.
 */
class NoBoxesAvailableException extends RuntimeException
{
    public function __construct(string $message, private readonly ItemList $itemList)
    {
        parent::__construct($message);
    }

    public function getAffectedItems(): ItemList
    {
        return $this->itemList;
    }
}
