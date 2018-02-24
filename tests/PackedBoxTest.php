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
        $item = new OrientatedItem(new TestItem('Item', 230, 330, 6, 320, true), 230, 330, 6);

        $packedItemList = new PackedItemList();
        $packedItemList->insert(PackedItem::fromOrientatedItem($item, 0, 0, 0));

        $packedBox = PackedBox::fromPackedItemList($box, $packedItemList);

        self::assertEquals($box, $packedBox->getBox());
        self::assertEquals($packedItemList->asItemList(), $packedBox->getItems());
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
        $item = new OrientatedItem(new TestItem('Item', 4, 10, 10, 10, true), 4, 10, 10);

        $boxItems = new PackedItemList();
        $boxItems->insert(PackedItem::fromOrientatedItem($item, 0, 0, 0));

        $packedBox = PackedBox::fromPackedItemList($box, $boxItems);

        self::assertEquals(400, $packedBox->getUsedVolume());
        self::assertEquals(1600, $packedBox->getUnusedVolume());
        self::assertEquals(20, $packedBox->getVolumeUtilisation());
    }

    /**
     * Test that caching of weight calculation works correctly.
     */
    public function testWeightCalcCaching()
    {
        $box = new TestBox('Box', 10, 10, 20, 10, 10, 10, 20, 10);
        $item = new OrientatedItem(new TestItem('Item', 4, 10, 10, 10, true), 4, 10, 10);

        $boxItems = new PackedItemList();
        $boxItems->insert(PackedItem::fromOrientatedItem($item, 0, 0, 0));

        $packedBox = PackedBox::fromPackedItemList($box, $boxItems);

        self::assertEquals(10, $packedBox->getItemWeight());

        //inspect cache, then poke at the value and see if it's returned correctly
        $cachedValue = new \ReflectionProperty($packedBox, 'itemWeight');
        $cachedValue->setAccessible(true);
        $cachedValue->getValue($packedBox);
        self::assertEquals(10, $cachedValue->getValue($packedBox));

        $cachedValue->setValue($packedBox, 30);
        self::assertEquals(30, $cachedValue->getValue($packedBox));
    }
}
