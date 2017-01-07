<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

class PackedBoxListTest extends \PHPUnit_Framework_TestCase
{
    function testVolumeUtilisation()
    {
        $box = new TestBox('Box', 10, 10, 10, 10, 10, 10, 10, 10);
        $item = new TestItem('Item', 5, 10, 10, 10, true);

        $boxItems = new ItemList();
        $boxItems->insert($item);

        $packedBox = new PackedBox($box, $boxItems, 1, 2, 3, 4, 0, 0, 0);

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insert($packedBox);

        self::assertEquals(50, $packedBoxList->getVolumeUtilisation());
    }

    function testWeightVariance()
    {
        $box = new TestBox('Box', 10, 10, 10, 10, 10, 10, 10, 10);
        $item = new TestItem('Item', 5, 10, 10, 10, true);

        $boxItems = new ItemList();
        $boxItems->insert($item);

        $packedBox = new PackedBox($box, $boxItems, 1, 2, 3, 4, 0, 0, 0);

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insert($packedBox);

        self::assertEquals(0, $packedBoxList->getWeightVariance());
    }
}
