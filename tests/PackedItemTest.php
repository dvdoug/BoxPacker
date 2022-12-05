<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestItem;
use DVDoug\BoxPacker\Test\THPackTestItem;
use PHPUnit\Framework\TestCase;

use function json_encode;

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
        $packedItem = new PackedItem(new TestItem('Item', 1, 1, 0, 0, Rotation::BestFit), 0, 0, 0, 3, 5, 7);
        self::assertSame(105, $packedItem->getVolume());
    }

    public function testJsonSerializeWithItemSupportingJsonSerializeIterable(): void
    {
        $item = new TestItem('Item', 1, 2, 3, 10, Rotation::BestFit);
        $packedItem = new PackedItem($item, 100, 20, 300, 3, 5, 7);
        self::assertJsonStringEqualsJsonString('{"x":100,"y":20,"z":300,"width":3,"length":5,"depth":7,"item":{"description":"Item","width":1,"length":2,"depth":3,"allowedRotation":6, "weight":10}}', json_encode($packedItem));
    }

    public function testJsonSerializeWithItemSupportingJsonSerializeNonIterable(): void
    {
        $item = new TestItem('Item', 1, 2, 3, 10, Rotation::BestFit);
        $item->setJsonSerializeOverride('some custom thing');
        $packedItem = new PackedItem($item, 100, 20, 300, 3, 5, 7);
        self::assertJsonStringEqualsJsonString('{"x":100,"y":20,"z":300,"width":3,"length":5,"depth":7,"item":{"description":"Item","width":1,"length":2,"depth":3,"allowedRotation":6, "extra":"some custom thing"}}', json_encode($packedItem));
    }

    public function testJsonSerializeWithItemSupportingNotSupportingJsonSerialize(): void
    {
        $item = new THPackTestItem('Item', 1, true, 2, true, 3, true);
        $packedItem = new PackedItem($item, 100, 20, 300, 3, 5, 7);
        self::assertJsonStringEqualsJsonString('{"x":100,"y":20,"z":300,"width":3,"length":5,"depth":7,"item":{"description":"Item","width":1,"length":2,"depth":3,"allowedRotation":6}}', json_encode($packedItem));
    }
}
