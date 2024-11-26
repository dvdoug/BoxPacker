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
readonly class PackedItem implements JsonSerializable
{
    public int $volume;

    public function __construct(
        public Item $item,
        public int $x,
        public int $y,
        public int $z,
        public int $width,
        public int $length,
        public int $depth
    ) {
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
