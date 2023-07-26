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
    public function testGetters(): void
    {
        $packedItem = new PackedItem(new TestItem('Item', 11, 22, 33, 43, Rotation::BestFit), 4, 5, 6, 33, 11, 22);
        $packedLayer = new PackedLayer();
        $packedLayer->insert($packedItem);
        self::assertSame(4, $packedLayer->getStartX());
        self::assertSame(37, $packedLayer->getEndX());
        self::assertSame(33, $packedLayer->getWidth());
        self::assertSame(5, $packedLayer->getStartY());
        self::assertSame(16, $packedLayer->getEndY());
        self::assertSame(11, $packedLayer->getLength());
        self::assertSame(6, $packedLayer->getStartZ());
        self::assertSame(28, $packedLayer->getEndZ());
        self::assertSame(22, $packedLayer->getDepth());
        self::assertSame(363, $packedLayer->getFootprint());
        self::assertSame(43, $packedLayer->getWeight());
    }
}
