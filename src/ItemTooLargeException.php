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
class ItemTooLargeException extends RuntimeException
{
    /** @var Item */
    public $item;

    /**
     * ItemTooLargeException constructor.
     */
    public function __construct(string $message, Item $item)
    {
        $this->item = $item;
        parent::__construct($message);
    }

    public function getItem(): Item
    {
        return $this->item;
    }
}
