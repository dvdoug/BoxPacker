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

class PackedBoxListTest extends TestCase
{
    public function testVolumeUtilisation()
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

    public function testWeightVariance()
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
