<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DVDoug\BoxPacker\PackedItem
 */
class PackedItemTest extends TestCase
{
    /**
     * Test volume calculation.
     */
    public function testVolumeCalculation(): void
    {
        $packedItem = new PackedItem(new TestItem('Item', 1, 1, 0, 0, false), 0, 0, 0, 3, 5, 7);
        self::assertSame(105, $packedItem->getVolume());
    }
}
