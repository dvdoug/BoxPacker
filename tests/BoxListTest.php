<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestBox;
use function iterator_to_array;
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
    public function testSorting(): void
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
     * Test that when there are spatially identical boxes that hold the same contents, prefer the one that weighs least.
     */
    public function testPickLighterBoxAllElseEqual(): void
    {
        $box1 = new TestBox('Strong Box', 200, 200, 200, 20, 200, 200, 200, 500);
        $box2 = new TestBox('Lightweight Box', 200, 200, 200, 5, 200, 200, 200, 200);

        $list = new BoxList();
        $list->insert($box1);
        $list->insert($box2);

        $sorted = iterator_to_array($list, false);
        self::assertEquals([$box2, $box1], $sorted);
    }

    /**
     * Test that items with a volume greater than 2^31-1 (max signed integer) are sorted correctly.
     */
    public function testIssue30A(): void
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
    public function testIssue30B(): void
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

    /**
     * Test that sorting of boxes with identical dimensions works as expected i.e. order by maximum weight capacity.
     */
    public function testIssue163(): void
    {
        $boxA = new TestBox('Box A', 202, 152, 32, 10, 200, 150, 30, 100);
        $boxB = new TestBox('Box B', 202, 152, 32, 5, 200, 150, 30, 100);
        $boxC = new TestBox('Box C', 202, 152, 32, 10, 200, 150, 30, 250);
        $boxD = new TestBox('Box D', 202, 152, 32, 10, 200, 150, 30, 50);
        $boxE = new TestBox('Box E', 202, 152, 32, 10, 200, 150, 30, 90);

        $list = new BoxList();
        $list->insert($boxA);
        $list->insert($boxB);
        $list->insert($boxC);
        $list->insert($boxD);
        $list->insert($boxE);

        $sorted = iterator_to_array($list, false);
        self::assertEquals([$boxB, $boxD, $boxE, $boxA, $boxC], $sorted);
    }
}
