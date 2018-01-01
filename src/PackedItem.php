<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

/**
 * A packed item.
 *
 * @author Doug Wright
 */
class PackedItem
{
    /**
     * @var int
     */
    protected $x;

    /**
     * @var int
     */
    protected $y;

    /**
     * @var int
     */
    protected $z;

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
     * PackedItem constructor.
     *
     * @param Item $item
     * @param int  $x
     * @param int  $y
     * @param int  $z
     * @param int  $width
     * @param int  $length
     * @param int  $depth
     */
    public function __construct(Item $item, $x, $y, $z, $width, $length, $depth)
    {
        $this->item = $item;
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
        $this->width = $width;
        $this->length = $length;
        $this->depth = $depth;
    }

    /**
     * @return int
     */
    public function getX()
    {
        return $this->x;
    }

    /**
     * @return int
     */
    public function getY()
    {
        return $this->y;
    }

    /**
     * @return int
     */
    public function getZ()
    {
        return $this->z;
    }

    /**
     * @return Item
     */
    public function getItem()
    {
        return $this->item;
    }

    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * @return int
     */
    public function getDepth()
    {
        return $this->depth;
    }

    /**
     * @param OrientatedItem $orientatedItem
     * @param int            $x
     * @param int            $y
     * @param int            $z
     *
     * @return PackedItem
     */
    public static function fromOrientatedItem(OrientatedItem $orientatedItem, $x, $y, $z)
    {
        return new static(
            $orientatedItem->getItem(),
            $x,
            $y,
            $z,
            $orientatedItem->getWidth(),
            $orientatedItem->getLength(),
            $orientatedItem->getDepth()
        );
    }

    /**
     * @return OrientatedItem
     */
    public function toOrientatedItem()
    {
        return new OrientatedItem($this->item, $this->width, $this->length, $this->depth);
    }
}
