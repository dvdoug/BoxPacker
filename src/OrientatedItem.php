<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
namespace DVDoug\BoxPacker;

use JsonSerializable;

/**
 * An item to be packed.
 *
 * @author Doug Wright
 */
class OrientatedItem implements JsonSerializable
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
     * @var int
     */
    protected $surfaceFootprint;

    /**
     * @var bool[]
     */
    protected static $stabilityCache = [];

    /**
     * @var array
     */
    protected $dimensionsAsArray;

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
        $this->surfaceFootprint = $width * $length;

        $this->dimensionsAsArray = [$width, $length, $depth];
        sort($this->dimensionsAsArray);
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
     * Calculate the surface footprint of the current orientation.
     *
     * @return int
     */
    public function getSurfaceFootprint()
    {
        return $this->surfaceFootprint;
    }

    /**
     * Is this item stable (low centre of gravity), calculated as if the tipping point is >15 degrees.
     *
     * N.B. Assumes equal weight distribution.
     *
     * @return bool
     */
    public function isStable()
    {
        $cacheKey = $this->width . '|' . $this->length . '|' . $this->depth;

        if (!isset(static::$stabilityCache[$cacheKey])) {
            static::$stabilityCache[$cacheKey] = (atan(min($this->length, $this->width) / ($this->depth ?: 1)) > 0.261);
        }

         return static::$stabilityCache[$cacheKey];
    }

    /**
     * Is the supplied item the same size as this one?
     *
     * @internal
     */
    public function isSameDimensions(Item $item)
    {
        if ($item === $this->item) {
            return true;
        }

        $itemDimensions = [$item->getWidth(), $item->getLength(), $item->getDepth()];
        sort($itemDimensions);

        return $this->dimensionsAsArray === $itemDimensions;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return [
            'item' => $this->item,
            'width' => $this->width,
            'length' => $this->length,
            'depth' => $this->depth,
        ];
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->width . '|' . $this->length . '|' . $this->depth;
    }
}
