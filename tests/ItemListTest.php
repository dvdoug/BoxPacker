<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DVDoug\BoxPacker\ItemList
 */
class ItemListTest extends TestCase
{
    /**
     * Test that sorting of items with different dimensions works as expected i.e.
     * - Largest (by volume) first
     * - If identical volume, sort by weight.
     */
    public function testDimensionalSorting()
    {
        $item1 = new TestItem('Small', 20, 20, 2, 100, true);
        $item2 = new TestItem('Large', 200, 200, 20, 1000, true);
        $item3 = new TestItem('Medium', 100, 100, 10, 500, true);
        $item4 = new TestItem('Medium Heavy', 100, 100, 10, 501, true);

        $list = new ItemList();
        $list->insert($item1);
        $list->insert($item2);
        $list->insert($item3);
        $list->insert($item4);

        $sorted = iterator_to_array($list, false);
        self::assertEquals([$item2, $item4, $item3, $item1], $sorted);
    }

    /**
     * Test that sorting of items with identical dimensions works as expected i.e.
     * - Items with the same name (i.e. same type) are kept together.
     */
    public function testKeepingItemsOfSameTypeTogether()
    {
        $item1 = new TestItem('Item A', 20, 20, 2, 100, true);
        $item2 = new TestItem('Item B', 20, 20, 2, 100, true);
        $item3 = new TestItem('Item A', 20, 20, 2, 100, true);
        $item4 = new TestItem('Item B', 20, 20, 2, 100, true);

        $list = new ItemList();
        $list->insert($item1);
        $list->insert($item2);
        $list->insert($item3);
        $list->insert($item4);

        $sorted = iterator_to_array($list, false);
        self::assertEquals([$item1, $item3, $item2, $item4], $sorted);
    }

    /**
     * Test that we can retrieve an accurate count of items in the list.
     */
    public function testCount()
    {
        $itemList = new ItemList();
        self::assertEquals(0, count($itemList));

        $item1 = new TestItem('Item A', 20, 20, 2, 100, true);
        $itemList->insert($item1);
        self::assertEquals(1, count($itemList));

        $item2 = new TestItem('Item B', 20, 20, 2, 100, true);
        $itemList->insert($item2);
        self::assertEquals(2, count($itemList));

        $item3 = new TestItem('Item C', 20, 20, 2, 100, true);
        $itemList->insert($item3);
        self::assertEquals(3, count($itemList));
    }

    /**
     * Test we can peek at the "top" (next) item in the list.
     */
    public function testTop()
    {
        $itemList = new ItemList();
        $item1 = new TestItem('Item A', 20, 20, 2, 100, true);
        $itemList->insert($item1);

        self::assertEquals($item1, $itemList->top());
        self::assertEquals(1, count($itemList));
    }

    /**
     * Test we can retrieve the "top" (next) item in the list.
     */
    public function testExtract()
    {
        $itemList = new ItemList();
        $item1 = new TestItem('Item A', 20, 20, 2, 100, true);
        $itemList->insert($item1);

        self::assertEquals($item1, $itemList->extract());
        self::assertEquals(0, count($itemList));
    }
}
