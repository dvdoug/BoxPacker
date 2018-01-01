<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

/**
 * An item to be packed.
 *
 * @author Doug Wright
 */
class OrientatedItem
{
    /**
     * @var Item
     */
    protected $item;

    /**
     * @var int
     */
    protected $width;

    /**
     * @var int
     */
    protected $length;

    /**
     * @var int
     */
    protected $depth;

    /**
     * Constructor.
     *
     * @param Item $item
     * @param int  $width
     * @param int  $length
     * @param int  $depth
     */
    public function __construct(Item $item, $width, $length, $depth)
    {
        $this->item = $item;
        $this->width = $width;
        $this->length = $length;
        $this->depth = $depth;
    }

    /**
     * Item.
     *
     * @return Item
     */
    public function getItem()
    {
        return $this->item;
    }

    /**
     * Item width in mm in it's packed orientation.
     *
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * Item length in mm in it's packed orientation.
     *
     * @return int
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * Item depth in mm in it's packed orientation.
     *
     * @return int
     */
    public function getDepth()
    {
        return $this->depth;
    }

    /**
     * Is this orientation stable (low centre of gravity)
     * N.B. Assumes equal weight distribution.
     *
     * @return bool
     */
    public function isStable()
    {
        return $this->getDepth() <= min($this->getLength(), $this->getWidth());
    }
}
