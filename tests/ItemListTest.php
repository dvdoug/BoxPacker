<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

class ItemListTest extends \PHPUnit_Framework_TestCase
{

    function testCompare()
    {

        $box1 = new TestItem('Small', 20, 20, 2, 100, true);
        $box2 = new TestItem('Large', 200, 200, 20, 1000, true);
        $box3 = new TestItem('Medium', 100, 100, 10, 500, true);

        $list = new ItemList;
        $list->insert($box1);
        $list->insert($box2);
        $list->insert($box3);

        $sorted = [];
        while (!$list->isEmpty()) {
            $sorted[] = $list->extract();
        }
        self::assertEquals(array($box2, $box3, $box1), $sorted);
    }
}
