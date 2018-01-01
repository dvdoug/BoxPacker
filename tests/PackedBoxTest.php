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

class PackedBoxTest extends TestCase
{
    public function testGetters()
    {
        $box = new TestBox('Box', 370, 375, 60, 140, 364, 374, 40, 3000);
        $item = new TestItem('Item', 230, 330, 6, 320, true);

        $boxItems = new ItemList();
        $boxItems->insert($item);

        $packedBox = new PackedBox($box, $boxItems, 1, 2, 3, 4, 0, 0, 0);

        self::assertEquals(1, $packedBox->getRemainingWidth());
        self::assertEquals(2, $packedBox->getRemainingLength());
        self::assertEquals(3, $packedBox->getRemainingDepth());
        self::assertEquals(4, $packedBox->getRemainingWeight());
    }

    public function testVolumeUtilisation()
    {
        $box = new TestBox('Box', 10, 10, 10, 10, 10, 10, 10, 10);
        $item = new TestItem('Item', 5, 10, 10, 10, true);

        $boxItems = new ItemList();
        $boxItems->insert($item);

        $packedBox = new PackedBox($box, $boxItems, 1, 2, 3, 4, 0, 0, 0);

        self::assertEquals(50, $packedBox->getVolumeUtilisation());
    }
}
