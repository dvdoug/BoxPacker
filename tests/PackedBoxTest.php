<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\LimitedSupplyTestBox;
use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[CoversClass(PackedBox::class)]
class PackedBoxTest extends TestCase
{
    /**
     * Test various getters work correctly.
     */
    public function testGetters(): void
    {
        $box = new TestBox('Box', 370, 375, 60, 140, 364, 374, 40, 3000);
        $item = new OrientatedItem(new TestItem('Item', 230, 330, 6, 320, Rotation::BestFit), 230, 330, 6);

        $packedItemList = new PackedItemList();
        $packedItemList->insert(PackedItem::fromOrientatedItem($item, 0, 0, 0));

        $packedBox = new PackedBox($box, $packedItemList);

        self::assertEquals($box, $packedBox->box);
        self::assertEquals($packedItemList, $packedBox->items);
        self::assertEquals(460, $packedBox->getWeight());
        self::assertEquals(134, $packedBox->getRemainingWidth());
        self::assertEquals(44, $packedBox->getRemainingLength());
        self::assertEquals(34, $packedBox->getRemainingDepth());
        self::assertEquals(2540, $packedBox->getRemainingWeight());
        self::assertEquals(5445440, $packedBox->getInnerVolume());
    }

    /**
     * Test that volume utilisation is calculated correctly.
     */
    public function testVolumeUtilisation(): void
    {
        $box = new TestBox('Box', 10, 10, 20, 10, 10, 10, 20, 10);
        $item = new OrientatedItem(new TestItem('Item', 4, 10, 10, 10, Rotation::BestFit), 4, 10, 10);

        $boxItems = new PackedItemList();
        $boxItems->insert(PackedItem::fromOrientatedItem($item, 0, 0, 0));

        $packedBox = new PackedBox($box, $boxItems);

        self::assertEquals(400, $packedBox->getUsedVolume());
        self::assertEquals(1600, $packedBox->getUnusedVolume());
        self::assertEquals(20, $packedBox->getVolumeUtilisation());
    }

    /**
     * Test that weight calculation works correctly.
     */
    public function testWeightCalc(): void
    {
        $box = new TestBox('Box', 10, 10, 20, 10, 10, 10, 20, 10);
        $item = new OrientatedItem(new TestItem('Item', 4, 10, 10, 10, Rotation::BestFit), 4, 10, 10);

        $boxItems = new PackedItemList();
        $boxItems->insert(PackedItem::fromOrientatedItem($item, 0, 0, 0));

        $packedBox = new PackedBox($box, $boxItems);

        self::assertEquals(10, $packedBox->getItemWeight());
    }

    public function testJsonSerializeWithBoxSupportingJsonSerializeIterable(): void
    {
        $box = new TestBox('Box', 10, 10, 20, 10, 10, 10, 20, 10);
        $item = new OrientatedItem(new TestItem('Item', 4, 10, 10, 10, Rotation::KeepFlat), 4, 10, 10);

        $boxItems = new PackedItemList();
        $boxItems->insert(PackedItem::fromOrientatedItem($item, 0, 0, 0));

        $packedBox = new PackedBox($box, $boxItems);

        self::assertJsonStringEqualsJsonString('{"box":{"reference":"Box","innerWidth":10,"innerLength":10,"innerDepth":20,"emptyWeight": 10,"maxWeight": 10},"items":[{"x":0,"y":0,"z":0,"width":4,"length":10,"depth":10,"item":{"description":"Item","width":4,"length":10,"depth":10,"allowedRotation":2,"weight": 10}}]}', json_encode($packedBox));
    }

    public function testJsonSerializeWithBoxSupportingJsonSerializeNonIterable(): void
    {
        $box = new TestBox('Box', 10, 10, 20, 10, 10, 10, 20, 10);
        $box->setJsonSerializeOverride('some custom thing');
        $item = new OrientatedItem(new TestItem('Item', 4, 10, 10, 10, Rotation::KeepFlat), 4, 10, 10);

        $boxItems = new PackedItemList();
        $boxItems->insert(PackedItem::fromOrientatedItem($item, 0, 0, 0));

        $packedBox = new PackedBox($box, $boxItems);

        self::assertJsonStringEqualsJsonString('{"box":{"reference":"Box","innerWidth":10,"innerLength":10,"innerDepth":20,"extra":"some custom thing"},"items":[{"x":0,"y":0,"z":0,"width":4,"length":10,"depth":10,"item":{"description":"Item","width":4,"length":10,"depth":10,"allowedRotation":2,"weight": 10}}]}', json_encode($packedBox));
    }

    public function testJsonSerializeWithBoxNotSupportingJsonSerialize(): void
    {
        $box = new LimitedSupplyTestBox('Box', 10, 10, 20, 10, 10, 10, 20, 10, 1);
        $item = new OrientatedItem(new TestItem('Item', 4, 10, 10, 10, Rotation::KeepFlat), 4, 10, 10);

        $boxItems = new PackedItemList();
        $boxItems->insert(PackedItem::fromOrientatedItem($item, 0, 0, 0));

        $packedBox = new PackedBox($box, $boxItems);

        self::assertJsonStringEqualsJsonString('{"box":{"reference":"Box","innerWidth":10,"innerLength":10,"innerDepth":20},"items":[{"x":0,"y":0,"z":0,"width":4,"length":10,"depth":10,"item":{"description":"Item","width":4,"length":10,"depth":10,"allowedRotation":2,"weight": 10}}]}', json_encode($packedBox));
    }

    /**
     * Test visualisation URL.
     */
    public function testVisualisationURL(): void
    {
        $box = new TestBox('Box', 10, 10, 20, 10, 10, 10, 20, 10);
        $item = new OrientatedItem(new TestItem('Item', 4, 10, 10, 10, Rotation::KeepFlat), 4, 10, 10);

        $boxItems = new PackedItemList();
        $boxItems->insert(PackedItem::fromOrientatedItem($item, 0, 0, 0));

        $packedBox = new PackedBox($box, $boxItems);

        self::assertEquals('https://boxpacker.io/en/master/visualiser.html?packing=%7B%22items%22%3A%5B%5B%22Item%22%2C4%2C10%2C10%5D%5D%2C%22boxes%22%3A%5B%5B%22Box%22%2C10%2C10%2C20%2C%5B%5B0%2C0%2C0%2C0%2C4%2C10%2C10%5D%5D%5D%5D%7D', $packedBox->generateVisualisationURL());
    }
}
