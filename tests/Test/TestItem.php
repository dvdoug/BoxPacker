<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker\Test;

use DVDoug\BoxPacker\Item;
use stdClass;

class TestItem implements Item
{
    /**
     * @var string
     */
    private $description;

    /**
     * @var int
     */
    private $width;

    /**
     * @var int
     */
    private $length;

    /**
     * @var int
     */
    private $depth;

    /**
     * @var int
     */
    private $weight;

    /**
     * @var int
     */
    private $keepFlat;

    /**
     * @var int
     */
    private $volume;

    /* Test objects that recurse
     * @var stdClass
     */
    private $a;

    /**
     * Test objects that recurse.
     *
     * @var stdClass
     */
    private $b;

    /**
     * TestItem constructor.
     *
     * @param string $description
     * @param int    $width
     * @param int    $length
     * @param int    $depth
     * @param int    $weight
     * @param bool   $keepFlat
     */
    public function __construct(
        $description,
        $width,
        $length,
        $depth,
        $weight,
        $keepFlat)
    {
        $this->description = $description;
        $this->width = $width;
        $this->length = $length;
        $this->depth = $depth;
        $this->weight = $weight;
        $this->keepFlat = $keepFlat;

        $this->volume = $this->width * $this->length * $this->depth;
        $this->a = new stdClass();
        $this->b = new stdClass();

        $this->a->b = $this->b;
        $this->b->a = $this->a;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
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
     * @return int
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @return int
     */
    public function getVolume()
    {
        return $this->volume;
    }

    /**
     * @return bool
     */
    public function getKeepFlat()
    {
        return $this->keepFlat;
    }
}
