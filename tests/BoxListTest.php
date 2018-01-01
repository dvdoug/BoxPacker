<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestBox;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DVDoug\BoxPacker\BoxList
 */
class BoxListTest extends TestCase
{
    /**
     * Test that sorting of boxes with different dimensions works as expected i.e.
     * - Largest (by volume) first.
     */
    public function testSorting()
    {
        $box1 = new TestBox('Small', 21, 21, 3, 1, 20, 20, 2, 100);
        $box2 = new TestBox('Large', 201, 201, 21, 1, 200, 200, 20, 1000);
        $box3 = new TestBox('Medium', 101, 101, 11, 5, 100, 100, 10, 500);

        $list = new BoxList();
        $list->insert($box1);
        $list->insert($box2);
        $list->insert($box3);

        $sorted = iterator_to_array($list, false);
        self::assertEquals([$box1, $box3, $box2], $sorted);
    }

    /**
     * Test that items with a volume greater than 2^31-1 (max signed integer) are sorted correctly.
     */
    public function testIssue30A()
    {
        $box1 = new TestBox('Small', 21, 21, 3, 1, 20, 20, 2, 100);
        $box2 = new TestBox('Large', 1301, 1301, 1301, 1, 1300, 1300, 1300, 1000);
        $box3 = new TestBox('Medium', 101, 101, 11, 5, 100, 100, 10, 500);
        $list = new BoxList();
        $list->insert($box1);
        $list->insert($box2);
        $list->insert($box3);

        $sorted = iterator_to_array($list, false);
        self::assertEquals([$box1, $box3, $box2], $sorted);
    }

    /**
     * Test that items with a volume greater than 2^31-1 (max signed integer) are sorted correctly.
     */
    public function testIssue30B()
    {
        $box1 = new TestBox('Small', 21, 21, 3, 1, 20, 20, 2, 100);
        $box2 = new TestBox('Large', 1301, 1301, 1301, 1, 1300, 1300, 1300, 1000);
        $box3 = new TestBox('Medium', 101, 101, 11, 5, 100, 100, 10, 500);
        $list = new BoxList();
        $list->insert($box3);
        $list->insert($box2);
        $list->insert($box1);

        $sorted = iterator_to_array($list, false);
        self::assertEquals([$box1, $box3, $box2], $sorted);

        $list = new BoxList();
        $list->insert($box2);
        $list->insert($box1);
        $list->insert($box3);

        $sorted = iterator_to_array($list, false);
        self::assertEquals([$box1, $box3, $box2], $sorted);
    }
}
