<?php

/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
namespace DVDoug\BoxPacker;

/**
 * Class ItemTooLargeException
 * Exception used when an item is too large to pack
 *
 * @package DVDoug\BoxPacker
 */
class ItemTooLargeException extends \RuntimeException
{

    /** @var Item */
    public $item;

    /**
     * ItemTooLargeException constructor.
     *
     * @param string $message
     * @param Item   $item
     */
    public function __construct($message, Item $item) {
        $this->item = $item;
        parent::__construct($message);
    }

    /**
     * @return Item
     */
    public function getItem() {
        return $this->item;
    }

}
