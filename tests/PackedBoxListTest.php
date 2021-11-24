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

/**
 * @covers \DVDoug\BoxPacker\PackedBoxList
 */
class PackedBoxListTest extends TestCase
{
    /**
     * Test that inserting individually correctly works.
     */
    public function testInsertAndCount(): void
    {
        $box = new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 100);
        $itemA = new TestItem('Item A', 5, 10, 10, 10, TestItem::ROTATION_BEST_FIT);
        $itemB = new TestItem('Item B', 5, 10, 10, 20, TestItem::ROTATION_BEST_FIT);

        $packedItemA = new PackedItem($itemA, 0, 0, 0, 5, 10, 10);
        $packedItemB = new PackedItem($itemB, 0, 0, 0, 5, 10, 10);

        $packedItemListA = new PackedItemList();
        $packedItemListA->insert($packedItemA);
        $packedBoxA = new PackedBox($box, $packedItemListA);

        $packedItemListB = new PackedItemList();
        $packedItemListB->insert($packedItemB);
        $packedBoxB = new PackedBox($box, $packedItemListB);

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insert($packedBoxA);
        $packedBoxList->insert($packedBoxB);

        self::assertCount(2, $packedBoxList);
    }

    /**
     * Test that inserting in bulk correctly works.
     */
    public function testInsertFromArrayAndCount(): void
    {
        $box = new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 100);
        $itemA = new TestItem('Item A', 5, 10, 10, 10, TestItem::ROTATION_BEST_FIT);
        $itemB = new TestItem('Item B', 5, 10, 10, 20, TestItem::ROTATION_BEST_FIT);

        $packedItemA = new PackedItem($itemA, 0, 0, 0, 5, 10, 10);
        $packedItemB = new PackedItem($itemB, 0, 0, 0, 5, 10, 10);

        $packedItemListA = new PackedItemList();
        $packedItemListA->insert($packedItemA);
        $packedBoxA = new PackedBox($box, $packedItemListA);

        $packedItemListB = new PackedItemList();
        $packedItemListB->insert($packedItemB);
        $packedBoxB = new PackedBox($box, $packedItemListB);

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insertFromArray([$packedBoxA, $packedBoxB]);

        self::assertCount(2, $packedBoxList);
    }

    /**
     * Test we can peek at the "top" (next) item in the list.
     */
    public function testTop(): void
    {
        $box = new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 100);
        $itemA = new TestItem('Item A', 5, 10, 10, 10, TestItem::ROTATION_BEST_FIT);
        $itemB = new TestItem('Item B', 5, 10, 10, 20, TestItem::ROTATION_BEST_FIT);

        $packedItemA = new PackedItem($itemA, 0, 0, 0, 5, 10, 10);
        $packedItemB = new PackedItem($itemB, 0, 0, 0, 5, 10, 10);

        $packedItemListA = new PackedItemList();
        $packedItemListA->insert($packedItemA);
        $packedBoxA = new PackedBox($box, $packedItemListA);

        $packedItemListB = new PackedItemList();
        $packedItemListB->insert($packedItemB);
        $packedBoxB = new PackedBox($box, $packedItemListB);

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insert($packedBoxA);
        $packedBoxList->insert($packedBoxB);

        self::assertEquals($packedBoxA, $packedBoxList->top());
    }

    /**
     * Test that volume utilisation is correctly calculated.
     */
    public function testVolumeUtilisation(): void
    {
        $box = new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 10);
        $item = new TestItem('Item', 5, 10, 10, 10, TestItem::ROTATION_BEST_FIT);

        $packedItem = new PackedItem($item, 0, 0, 0, 5, 10, 10);

        $packedItemList = new PackedItemList();
        $packedItemList->insert($packedItem);

        $packedBox = new PackedBox($box, $packedItemList);

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insert($packedBox);

        self::assertEquals(50, $packedBoxList->getVolumeUtilisation());
    }

    /**
     * Test that weight variance is correctly calculated.
     */
    public function testWeightVariance(): void
    {
        $box = new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 100);
        $itemA = new TestItem('Item A', 5, 10, 10, 10, TestItem::ROTATION_BEST_FIT);
        $itemB = new TestItem('Item B', 5, 10, 10, 20, TestItem::ROTATION_BEST_FIT);

        $packedItemA = new PackedItem($itemA, 0, 0, 0, 5, 10, 10);
        $packedItemB = new PackedItem($itemB, 0, 0, 0, 5, 10, 10);

        $packedItemListA = new PackedItemList();
        $packedItemListA->insert($packedItemA);
        $packedBoxA = new PackedBox($box, $packedItemListA);

        $packedItemListB = new PackedItemList();
        $packedItemListB->insert($packedItemB);
        $packedBoxB = new PackedBox($box, $packedItemListB);

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insert($packedBoxA);
        $packedBoxList->insert($packedBoxB);

        self::assertEquals(25, $packedBoxList->getWeightVariance());
    }

    /**
     * Test that mean weight is correctly calculated.
     */
    public function testMeanWeight(): void
    {
        $box = new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 100);
        $itemA = new TestItem('Item A', 5, 10, 10, 10, TestItem::ROTATION_BEST_FIT);
        $itemB = new TestItem('Item B', 5, 10, 10, 20, TestItem::ROTATION_BEST_FIT);

        $packedItemA = new PackedItem($itemA, 0, 0, 0, 5, 10, 10);
        $packedItemB = new PackedItem($itemB, 0, 0, 0, 5, 10, 10);

        $packedItemListA = new PackedItemList();
        $packedItemListA->insert($packedItemA);
        $packedBoxA = new PackedBox($box, $packedItemListA);

        $packedItemListB = new PackedItemList();
        $packedItemListB->insert($packedItemB);
        $packedBoxB = new PackedBox($box, $packedItemListB);

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insert($packedBoxA);
        $packedBoxList->insert($packedBoxB);

        self::assertEquals(15, $packedBoxList->getMeanWeight());
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

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insert($packedBox);

        self::assertJsonStringEqualsJsonString('[{"box":{"reference":"Box","innerWidth":10,"innerLength":10,"innerDepth":20},"items":[{"x":0,"y":0,"z":0,"width":4,"length":10,"depth":10,"item":{"description":"Item","width":4,"length":10,"depth":10,"allowedRotations":2}}]}]', json_encode($packedBoxList));
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

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insert($packedBox);

        self::assertEquals('https://boxpacker.io/en/master/visualiser.html?packing=%5B%7B%22box%22%3A%7B%22reference%22%3A%22Box%22%2C%22innerWidth%22%3A10%2C%22innerLength%22%3A10%2C%22innerDepth%22%3A20%7D%2C%22items%22%3A%5B%7B%22x%22%3A0%2C%22y%22%3A0%2C%22z%22%3A0%2C%22width%22%3A4%2C%22length%22%3A10%2C%22depth%22%3A10%2C%22item%22%3A%7B%22description%22%3A%22Item%22%2C%22width%22%3A4%2C%22length%22%3A10%2C%22depth%22%3A10%2C%22allowedRotations%22%3A2%7D%7D%5D%7D%5D', $packedBoxList->generateVisualisationURL());
    }
}
