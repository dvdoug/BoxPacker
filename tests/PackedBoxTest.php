<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
declare(strict_types=1);
namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\TestCase;

class PackedBoxTest extends TestCase
{
    function testGetters()
    {
        $box = new TestBox('Box', 370, 375, 60, 140, 364, 374, 40, 3000);
        $item = new OrientatedItem(new TestItem('Item', 230, 330, 6, 320, true), 230, 330, 6);

        $boxItems = new PackedItemList();
        $boxItems->insert(PackedItem::fromOrientatedItem($item, 0, 0, 0));

        $packedBox = new PackedBox($box, $boxItems);

        self::assertEquals(134, $packedBox->getRemainingWidth());
        self::assertEquals(44, $packedBox->getRemainingLength());
        self::assertEquals(34, $packedBox->getRemainingDepth());
        self::assertEquals(2540, $packedBox->getRemainingWeight());
    }

    function testVolumeUtilisation()
    {
        $box = new TestBox('Box', 10, 10, 10, 10, 10, 10, 10, 10);
        $item = new OrientatedItem(new TestItem('Item', 5, 10, 10, 10, true), 5,10,10);

        $boxItems = new PackedItemList();
        $boxItems->insert(PackedItem::fromOrientatedItem($item, 0, 0, 0));

        $packedBox = new PackedBox($box, $boxItems);

        self::assertEquals(50, $packedBox->getVolumeUtilisation());
    }
}
