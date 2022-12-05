<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use JsonSerializable;
use ReturnTypeWillChange;

use function array_merge;
use function is_array;

/**
 * A packed item.
 *
 * @author Doug Wright
 */
class PackedItem implements JsonSerializable
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
     */
    public function __construct(Item $item, int $x, int $y, int $z, int $width, int $length, int $depth)
    {
        $this->item = $item;
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
        $this->width = $width;
        $this->length = $length;
        $this->depth = $depth;
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function getZ(): int
    {
        return $this->z;
    }

    public function getItem(): Item
    {
        return $this->item;
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

    public function getVolume(): int
    {
        return $this->width * $this->length * $this->depth;
    }

    public static function fromOrientatedItem(OrientatedItem $orientatedItem, int $x, int $y, int $z): self
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
     * @deprecated
     */
    public function toOrientatedItem(): OrientatedItem
    {
        return new OrientatedItem($this->item, $this->width, $this->length, $this->depth);
    }

    #[ReturnTypeWillChange]
    public function jsonSerialize()/* : mixed */
    {
        $userValues = [];

        if ($this->item instanceof JsonSerializable) {
            $userSerialisation = $this->item->jsonSerialize();
            if (is_array($userSerialisation)) {
                $userValues = $userSerialisation;
            } else {
                $userValues = ['extra' => $userSerialisation];
            }
        }

        return [
            'x' => $this->x,
            'y' => $this->y,
            'z' => $this->z,
            'width' => $this->width,
            'length' => $this->length,
            'depth' => $this->depth,
            'item' => array_merge(
                $userValues,
                [
                    'description' => $this->item->getDescription(),
                    'width' => $this->item->getWidth(),
                    'length' => $this->item->getLength(),
                    'depth' => $this->item->getDepth(),
                    'keepFlat' => $this->item->getKeepFlat(),
                ]
            ),
        ];
    }
}
