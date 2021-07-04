<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestItem;
use function json_encode;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DVDoug\BoxPacker\PackedItem
 */
class PackedItemTest extends TestCase
{
    /**
     * Test volume calculation.
     */
    public function testVolumeCalculation(): void
    {
        $packedItem = new PackedItem(new TestItem('Item', 1, 1, 0, 0, false), 0, 0, 0, 3, 5, 7);
        self::assertSame(105, $packedItem->getVolume());
    }

    /**
     * Test JSON representation.
     */
    public function testJsonSerialize(): void
    {
        $packedItem = new PackedItem(new TestItem('Item', 1, 2, 3, 10, false), 100, 20, 300, 3, 5, 7);
        self::assertJsonStringEqualsJsonString('{"x":100,"y":20,"z":300,"width":3,"length":5,"depth":7,"item":{"description":"Item","width":1,"length":2,"depth":3,"keepFlat":false}}', json_encode($packedItem));
    }
}
