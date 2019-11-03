<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker\Test;

use DVDoug\BoxPacker\Item;
use JsonSerializable;
use stdClass;

class TestItem implements Item, JsonSerializable
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
     * Test objects that recurse.
     *
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
     */
    public function __construct(
        string $description,
        int $width,
        int $length,
        int $depth,
        int $weight,
        bool $keepFlat)
    {
        $this->description = $description;
        $this->width = $width;
        $this->length = $length;
        $this->depth = $depth;
        $this->weight = $weight;
        $this->keepFlat = $keepFlat;

        $this->a = new stdClass();
        $this->b = new stdClass();

        $this->a->b = $this->b;
        $this->b->a = $this->a;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function getKeepFlat(): bool
    {
        return $this->keepFlat;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return [
            'description' => $this->description,
            'width' => $this->width,
            'length' => $this->length,
            'depth' => $this->depth,
            'weight' => $this->weight,
            'keepFlat' => $this->keepFlat,
        ];
    }
}
