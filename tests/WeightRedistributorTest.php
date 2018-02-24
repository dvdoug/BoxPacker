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
 * @covers \DVDoug\BoxPacker\WeightRedistributor
 */
class WeightRedistributorTest extends TestCase
{
    /**
     * Test that a native 3+1 is repacked into 2+2.
     */
    public function testWeightRedistributionActivatesOrNot()
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 1, 1, 3, 0, 1, 1, 3, 3));
        $packer->addItem(new TestItem('Item', 1, 1, 1, 1, false), 4);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertEquals(2, $packedBoxes[0]->getItems()->count());
        self::assertEquals(2, $packedBoxes[1]->getItems()->count());
    }
}
