<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

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
    public function testInsertAndCount()
    {
        $box = new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 100);
        $itemA = new TestItem('Item A', 5, 10, 10, 10, true);
        $itemB = new TestItem('Item B', 5, 10, 10, 20, true);

        $packedItemListA = new ItemList();
        $packedItemListA->insert($itemA);
        $packedBoxA = new PackedBox($box, $packedItemListA, 0, 0, 0, 0, 0, 0, 0);

        $packedItemListB = new ItemList();
        $packedItemListB->insert($itemB);
        $packedBoxB = new PackedBox($box, $packedItemListB, 0, 0, 0, 0, 0, 0, 0);

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insert($packedBoxA);
        $packedBoxList->insert($packedBoxB);

        self::assertEquals(2, $packedBoxList->count());
    }

    /**
     * Test that inserting in bulk correctly works.
     */
    public function testInsertFromArrayAndCount()
    {
        $box = new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 100);
        $itemA = new TestItem('Item A', 5, 10, 10, 10, true);
        $itemB = new TestItem('Item B', 5, 10, 10, 20, true);

        $packedItemListA = new ItemList();
        $packedItemListA->insert($itemA);
        $packedBoxA = new PackedBox($box, $packedItemListA, 0, 0, 0, 0, 0, 0, 0);

        $packedItemListB = new ItemList();
        $packedItemListB->insert($itemB);
        $packedBoxB = new PackedBox($box, $packedItemListB, 0, 0, 0, 0, 0, 0, 0);

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insertFromArray([$packedBoxA, $packedBoxB]);

        self::assertEquals(2, $packedBoxList->count());
    }

    /**
     * Test we can peek at the "top" (next) item in the list.
     */
    public function testTop()
    {
        $box = new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 100);
        $itemA = new TestItem('Item A', 5, 10, 10, 10, true);
        $itemB = new TestItem('Item B', 5, 10, 10, 20, true);

        $packedItemListA = new ItemList();
        $packedItemListA->insert($itemA);
        $packedBoxA = new PackedBox($box, $packedItemListA, 0, 0, 0, 0, 0, 0, 0);

        $packedItemListB = new ItemList();
        $packedItemListB->insert($itemB);
        $packedBoxB = new PackedBox($box, $packedItemListB, 0, 0, 0, 0, 0, 0, 0);

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insert($packedBoxA);
        $packedBoxList->insert($packedBoxB);

        self::assertEquals($packedBoxB, $packedBoxList->top());
    }

    /**
     * Test that volume utilisation is correctly calculated.
     */
    public function testVolumeUtilisation()
    {
        $box = new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 10);
        $item = new TestItem('Item', 5, 10, 10, 10, true);

        $packedItemList = new ItemList();
        $packedItemList->insert($item);

        $packedBox = new PackedBox($box, $packedItemList, 0, 0, 0, 0, 0, 0, 0);

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insert($packedBox);

        self::assertEquals(50, $packedBoxList->getVolumeUtilisation());
    }

    /**
     * Test that weight variance is correctly calculated.
     */
    public function testWeightVariance()
    {
        $box = new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 100);
        $itemA = new TestItem('Item A', 5, 10, 10, 10, true);
        $itemB = new TestItem('Item B', 5, 10, 10, 20, true);

        $packedItemListA = new ItemList();
        $packedItemListA->insert($itemA);
        $packedBoxA = new PackedBox($box, $packedItemListA, 0, 0, 0, 0, 0, 0, 0);

        $packedItemListB = new ItemList();
        $packedItemListB->insert($itemB);
        $packedBoxB = new PackedBox($box, $packedItemListB, 0, 0, 0, 0, 0, 0, 0);

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insert($packedBoxA);
        $packedBoxList->insert($packedBoxB);

        self::assertEquals(25, $packedBoxList->getWeightVariance());
    }

    /**
     * Test that mean weight is correctly calculated.
     */
    public function testMeanWeight()
    {
        $box = new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 100);
        $itemA = new TestItem('Item A', 5, 10, 10, 10, true);
        $itemB = new TestItem('Item B', 5, 10, 10, 20, true);

        $packedItemListA = new ItemList();
        $packedItemListA->insert($itemA);
        $packedBoxA = new PackedBox($box, $packedItemListA, 0, 0, 0, 0, 0, 0, 0);

        $packedItemListB = new ItemList();
        $packedItemListB->insert($itemB);
        $packedBoxB = new PackedBox($box, $packedItemListB, 0, 0, 0, 0, 0, 0, 0);

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insert($packedBoxA);
        $packedBoxList->insert($packedBoxB);

        self::assertEquals(15, $packedBoxList->getMeanWeight());
    }
}
