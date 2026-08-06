<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\TestCase;

class PackedLayerTest extends TestCase
{
    public function testBoundsAfterInsert(): void
    {
        $packedItem = new PackedItem(new TestItem('Item', 11, 22, 33, 43, Rotation::BestFit), 4, 5, 6, 33, 11, 22);
        $packedLayer = new PackedLayer();
        $packedLayer->insert($packedItem);
        self::assertSame(4, $packedLayer->startX);
        self::assertSame(37, $packedLayer->endX);
        self::assertSame(33, $packedLayer->width);
        self::assertSame(5, $packedLayer->startY);
        self::assertSame(16, $packedLayer->endY);
        self::assertSame(11, $packedLayer->length);
        self::assertSame(6, $packedLayer->startZ);
        self::assertSame(28, $packedLayer->endZ);
        self::assertSame(22, $packedLayer->depth);
        self::assertSame(363, $packedLayer->footprint);
        self::assertSame(43, $packedLayer->weight);
    }

    public function testBoundsExpandOnInsertAndMerge(): void
    {
        $layer = new PackedLayer();
        self::assertSame(0, $layer->depth);
        self::assertSame(0, $layer->weight);

        $layer->insert(new PackedItem(new TestItem('A', 10, 10, 10, 1, Rotation::BestFit), 0, 0, 0, 10, 10, 10));
        self::assertSame(10, $layer->depth);
        self::assertSame(10, $layer->width);

        $other = new PackedLayer();
        $other->insert(new PackedItem(new TestItem('B', 5, 5, 20, 2, Rotation::BestFit), 5, 5, 5, 5, 5, 20));
        $layer->merge($other);

        self::assertSame(0, $layer->startX);
        self::assertSame(10, $layer->endX);
        self::assertSame(10, $layer->width);
        self::assertSame(0, $layer->startZ);
        self::assertSame(25, $layer->endZ);
        self::assertSame(25, $layer->depth);
        self::assertSame(3, $layer->weight);
    }
}
