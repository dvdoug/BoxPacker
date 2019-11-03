<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestItem;
use function iterator_to_array;
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
    public function testDimensionalSorting(): void
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
    public function testKeepingItemsOfSameTypeTogether(): void
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
    public function testCount(): void
    {
        $itemList = new ItemList();
        self::assertCount(0, $itemList);

        $item1 = new TestItem('Item A', 20, 20, 2, 100, true);
        $itemList->insert($item1);
        self::assertCount(1, $itemList);

        $item2 = new TestItem('Item B', 20, 20, 2, 100, true);
        $itemList->insert($item2);
        self::assertCount(2, $itemList);

        $item3 = new TestItem('Item C', 20, 20, 2, 100, true);
        $itemList->insert($item3);
        self::assertCount(3, $itemList);
    }

    /**
     * Test we can peek at the "top" (next) item in the list.
     */
    public function testTop(): void
    {
        $itemList = new ItemList();
        $item1 = new TestItem('Item A', 20, 20, 2, 100, true);
        $itemList->insert($item1);

        self::assertEquals($item1, $itemList->top());
        self::assertCount(1, $itemList);
    }

    /**
     * Test that we can retrieve an accurate count of items in the list.
     */
    public function testTopN(): void
    {
        $itemList = new ItemList();

        $item1 = new TestItem('Item A', 20, 20, 2, 100, true);
        $itemList->insert($item1);

        $item2 = new TestItem('Item B', 20, 20, 2, 100, true);
        $itemList->insert($item2);

        $item3 = new TestItem('Item C', 20, 20, 2, 100, true);
        $itemList->insert($item3);

        $top2 = $itemList->topN(2);

        self::assertCount(2, $top2);
        self::assertSame($item1, $top2->extract());
        self::assertSame($item2, $top2->extract());
    }

    /**
     * Test we can retrieve the "top" (next) item in the list.
     */
    public function testExtract(): void
    {
        $itemList = new ItemList();
        $item1 = new TestItem('Item A', 20, 20, 2, 100, true);
        $itemList->insert($item1);

        self::assertEquals($item1, $itemList->extract());
        self::assertCount(0, $itemList);
    }
}
