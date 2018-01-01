<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\TestCase;

class ItemListTest extends TestCase
{
    public function testCompare()
    {
        $item1 = new TestItem('Small', 20, 20, 2, 100);
        $item2 = new TestItem('Large', 200, 200, 20, 1000);
        $item3 = new TestItem('Medium', 100, 100, 10, 500);

        $list = new ItemList();
        $list->insert($item1);
        $list->insert($item2);
        $list->insert($item3);

        $sorted = iterator_to_array($list, false);
        self::assertEquals([$item2, $item3, $item1], $sorted);
    }

    public function testDifferentItemsSameDimensions()
    {
        $item1 = new TestItem('Item A', 20, 20, 2, 100);
        $item2 = new TestItem('Item B', 20, 20, 2, 100);
        $item3 = new TestItem('Item A', 20, 20, 2, 100);
        $item4 = new TestItem('Item B', 20, 20, 2, 100);

        $list = new ItemList();
        $list->insert($item1);
        $list->insert($item2);
        $list->insert($item3);
        $list->insert($item4);

        $sorted = iterator_to_array($list, false);
        self::assertEquals([$item1, $item3, $item2, $item4], $sorted);
    }
}
