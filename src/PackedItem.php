<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use JsonSerializable;

use function is_iterable;

/**
 * A packed item.
 */
class PackedItem implements JsonSerializable
{
    public function __construct(
        protected Item $item,
        protected int $x,
        protected int $y,
        protected int $z,
        protected int $width,
        protected int $length,
        protected int $depth
    ) {
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
        return new self(
            $orientatedItem->item,
            $x,
            $y,
            $z,
            $orientatedItem->width,
            $orientatedItem->length,
            $orientatedItem->depth,
        );
    }

    public function jsonSerialize(): array
    {
        $userValues = [];

        if ($this->item instanceof JsonSerializable) {
            $userSerialisation = $this->item->jsonSerialize();
            if (is_iterable($userSerialisation)) {
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
            'item' => [
                ...$userValues,
                'description' => $this->item->getDescription(),
                'width' => $this->item->getWidth(),
                'length' => $this->item->getLength(),
                'depth' => $this->item->getDepth(),
                'allowedRotation' => $this->item->getAllowedRotation(),
            ],
        ];
    }
}
