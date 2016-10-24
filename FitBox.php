<?php

namespace DVDoug\BoxPacker;


class FitBox implements Box
{

    /**
     * FitBox constructor.
     * @param Item $item
     */
    public function __construct(Item $item)
    {
        $this->item = $item;
    }

    /**
     * @var Item
     */
    protected $item;

    /**
     * Reference for box type (e.g. SKU or description)
     * @return string
     */
    public function getReference()
    {
        return $this->item->getDescription();
    }

    /**
     * Outer width in mm
     * @return int
     */
    public function getOuterWidth()
    {
        return $this->item->getWidth() + 1;
    }

    /**
     * Outer length in mm
     * @return int
     */
    public function getOuterLength()
    {
        return $this->item->getLength() + 1;
    }

    /**
     * Outer depth in mm
     * @return int
     */
    public function getOuterDepth()
    {
        return $this->item->getDepth() + 1;
    }

    /**
     * Empty weight in g
     * @return int
     */
    public function getEmptyWeight()
    {
        return 0;
    }

    /**
     * Inner width in mm
     * @return int
     */
    public function getInnerWidth()
    {
        return $this->item->getWidth() + 1;
    }

    /**
     * Inner length in mm
     * @return int
     */
    public function getInnerLength()
    {
        return $this->item->getLength() + 1;
    }

    /**
     * Inner depth in mm
     * @return int
     */
    public function getInnerDepth()
    {
        return $this->item->getDepth() + 1;
    }

    /**
     * Total inner volume of packing in mm^3
     * @return int
     */
    public function getInnerVolume()
    {
        return $this->getInnerDepth() * $this->getInnerLength() * $this->getInnerWidth();
    }

    /**
     * Max weight the packaging can hold in g
     * @return int
     */
    public function getMaxWeight()
    {
        return $this->item->getWeight() + 1;
    }
}