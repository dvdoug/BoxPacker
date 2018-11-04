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
        $itemA = new TestItem('Item A', 5, 10, 10, 10, true);
        $itemB = new TestItem('Item B', 5, 10, 10, 20, true);

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
        $itemA = new TestItem('Item A', 5, 10, 10, 10, true);
        $itemB = new TestItem('Item B', 5, 10, 10, 20, true);

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
        $itemA = new TestItem('Item A', 5, 10, 10, 10, true);
        $itemB = new TestItem('Item B', 5, 10, 10, 20, true);

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
        $item = new TestItem('Item', 5, 10, 10, 10, true);

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
        $itemA = new TestItem('Item A', 5, 10, 10, 10, true);
        $itemB = new TestItem('Item B', 5, 10, 10, 20, true);

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
        $itemA = new TestItem('Item A', 5, 10, 10, 10, true);
        $itemB = new TestItem('Item B', 5, 10, 10, 20, true);

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
}
