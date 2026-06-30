<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker\Test;

use DVDoug\BoxPacker\LinkedItem;
use DVDoug\BoxPacker\Rotation;

class LinkedTestItem extends TestItem implements LinkedItem
{
    public function __construct(
        string $description,
        int $width,
        int $length,
        int $depth,
        int $weight,
        Rotation $allowedRotation,
        private readonly string $linkedItemGroup,
    ) {
        parent::__construct($description, $width, $length, $depth, $weight, $allowedRotation);
    }

    public function getLinkedItemGroup(): string
    {
        return $this->linkedItemGroup;
    }
}
