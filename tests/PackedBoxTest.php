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

/**
 * @covers \DVDoug\BoxPacker\PackedBox
 */
class PackedBoxTest extends TestCase
{
    /**
     * Test various getters work correctly.
     */
    public function testGetters()
    {
        $box = new TestBox('Box', 370, 375, 60, 140, 364, 374, 40, 3000);
        $item = new TestItem('Item', 230, 330, 6, 320, true);

        $packedItemList = new ItemList();
        $packedItemList->insert($item);

        $packedBox = new PackedBox($box, $packedItemList, 134, 44, 34, 2540, 0, 0, 0);

        self::assertEquals($box, $packedBox->getBox());
        self::assertEquals($packedItemList, $packedBox->getItems());
        self::assertEquals(460, $packedBox->getWeight());
        self::assertEquals(134, $packedBox->getRemainingWidth());
        self::assertEquals(44, $packedBox->getRemainingLength());
        self::assertEquals(34, $packedBox->getRemainingDepth());
        self::assertEquals(2540, $packedBox->getRemainingWeight());
        self::assertEquals(5445440, $packedBox->getInnerVolume());
    }

    /**
     * Test that volume utilisation is calculated correctly.
     */
    public function testVolumeUtilisation()
    {
        $box = new TestBox('Box', 10, 10, 20, 10, 10, 10, 20, 10);
        $item = new TestItem('Item', 4, 10, 10, 10, true);

        $boxItems = new ItemList();
        $boxItems->insert($item);

        $packedBox = new PackedBox($box, $boxItems, 0, 0, 0,0,0, 0, 0);

        self::assertEquals(20, $packedBox->getVolumeUtilisation());
    }

    /**
     * Test that caching of weight calculation works correctly.
     */
    public function testWeightCalcCaching()
    {
        $box = new TestBox('Box', 10, 10, 20, 10, 10, 10, 20, 10);
        $item = new TestItem('Item', 4, 10, 10, 10, true);

        $boxItems = new ItemList();
        $boxItems->insert($item);

        $packedBox = new PackedBox($box, $boxItems, 0, 0, 0, 0, 0, 0, 0);

        self::assertEquals(20, $packedBox->getWeight());

        //inspect cache, then poke at the value and see if it's returned correctly
        $cachedValue = new \ReflectionProperty($packedBox, 'weight');
        $cachedValue->setAccessible(true);
        $cachedValue->getValue($packedBox);
        self::assertEquals(20, $cachedValue->getValue($packedBox));

        $cachedValue->setValue($packedBox, 30);
        self::assertEquals(30, $cachedValue->getValue($packedBox));
    }
}
