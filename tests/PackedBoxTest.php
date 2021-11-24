<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;
use function json_encode;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * @covers \DVDoug\BoxPacker\PackedBox
 */
class PackedBoxTest extends TestCase
{
    /**
     * Test various getters work correctly.
     */
    public function testGetters(): void
    {
        $box = new TestBox('Box', 370, 375, 60, 140, 364, 374, 40, 3000);
        $item = new OrientatedItem(new TestItem('Item', 230, 330, 6, 320, TestItem::ROTATION_BEST_FIT), 230, 330, 6);

        $packedItemList = new PackedItemList();
        $packedItemList->insert(PackedItem::fromOrientatedItem($item, 0, 0, 0));

        $packedBox = new PackedBox($box, $packedItemList);

        self::assertEquals($box, $packedBox->getBox());
        self::assertEquals($packedItemList, $packedBox->getItems());
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
        $item = new OrientatedItem(new TestItem('Item', 4, 10, 10, 10, TestItem::ROTATION_BEST_FIT), 4, 10, 10);

        $boxItems = new PackedItemList();
        $boxItems->insert(PackedItem::fromOrientatedItem($item, 0, 0, 0));

        $packedBox = new PackedBox($box, $boxItems);

        self::assertEquals(400, $packedBox->getUsedVolume());
        self::assertEquals(1600, $packedBox->getUnusedVolume());
        self::assertEquals(20, $packedBox->getVolumeUtilisation());
    }

    /**
     * Test that caching of weight calculation works correctly.
     */
    public function testWeightCalcCaching(): void
    {
        $box = new TestBox('Box', 10, 10, 20, 10, 10, 10, 20, 10);
        $item = new OrientatedItem(new TestItem('Item', 4, 10, 10, 10, TestItem::ROTATION_BEST_FIT), 4, 10, 10);

        $boxItems = new PackedItemList();
        $boxItems->insert(PackedItem::fromOrientatedItem($item, 0, 0, 0));

        $packedBox = new PackedBox($box, $boxItems);

        self::assertEquals(10, $packedBox->getItemWeight());

        //inspect cache, then poke at the value and see if it's returned correctly
        $cachedValue = new ReflectionProperty($packedBox, 'itemWeight');
        $cachedValue->setAccessible(true);
        self::assertEquals(10, $cachedValue->getValue($packedBox));

        $cachedValue->setValue($packedBox, 30);
        self::assertEquals(30, $cachedValue->getValue($packedBox));
    }

    /**
     * Test JSON representation.
     */
    public function testJsonSerialize(): void
    {
        $box = new TestBox('Box', 10, 10, 20, 10, 10, 10, 20, 10);
        $item = new OrientatedItem(new TestItem('Item', 4, 10, 10, 10, Item::ROTATION_KEEP_FLAT), 4, 10, 10);

        $boxItems = new PackedItemList();
        $boxItems->insert(PackedItem::fromOrientatedItem($item, 0, 0, 0));

        $packedBox = new PackedBox($box, $boxItems);

        self::assertJsonStringEqualsJsonString('{"box":{"reference":"Box","innerWidth":10,"innerLength":10,"innerDepth":20},"items":[{"x":0,"y":0,"z":0,"width":4,"length":10,"depth":10,"item":{"description":"Item","width":4,"length":10,"depth":10,"allowedRotations":2}}]}', json_encode($packedBox));
    }

    /**
     * Test visualisation URL.
     */
    public function testVisualisationURL(): void
    {
        $box = new TestBox('Box', 10, 10, 20, 10, 10, 10, 20, 10);
        $item = new OrientatedItem(new TestItem('Item', 4, 10, 10, 10, Item::ROTATION_KEEP_FLAT), 4, 10, 10);

        $boxItems = new PackedItemList();
        $boxItems->insert(PackedItem::fromOrientatedItem($item, 0, 0, 0));

        $packedBox = new PackedBox($box, $boxItems);

        self::assertEquals('https://boxpacker.io/en/master/visualiser.html?packing=%7B%22box%22%3A%7B%22reference%22%3A%22Box%22%2C%22innerWidth%22%3A10%2C%22innerLength%22%3A10%2C%22innerDepth%22%3A20%7D%2C%22items%22%3A%5B%7B%22x%22%3A0%2C%22y%22%3A0%2C%22z%22%3A0%2C%22width%22%3A4%2C%22length%22%3A10%2C%22depth%22%3A10%2C%22item%22%3A%7B%22description%22%3A%22Item%22%2C%22width%22%3A4%2C%22length%22%3A10%2C%22depth%22%3A10%2C%22allowedRotations%22%3A2%7D%7D%5D%7D', $packedBox->generateVisualisationURL());
    }
}
