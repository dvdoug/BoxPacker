<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\TestCase;

class PackedBoxListTest extends TestCase
{
    function testVolumeUtilisation()
    {
        $box = new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 10);
        $item = new TestItem('Item', 5, 10, 10, 10, true);

        $boxItems = new ItemList();
        $boxItems->insert($item);

        $packer = new VolumePacker($box, $boxItems);
        $packedBox = $packer->pack();

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insert($packedBox);

        self::assertEquals(50, $packedBoxList->getVolumeUtilisation());
    }

    function testWeightVariance()
    {
        $box = new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 10);
        $item = new TestItem('Item', 5, 10, 10, 10, true);

        $boxItems = new ItemList();
        $boxItems->insert($item);

        $packer = new VolumePacker($box, $boxItems);
        $packedBox = $packer->pack();

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insert($packedBox);

        self::assertEquals(0, $packedBoxList->getWeightVariance());
    }
}
