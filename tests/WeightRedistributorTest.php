<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\ConstrainedPlacementNoStackingTestItem;
use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;
use function iterator_to_array;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DVDoug\BoxPacker\WeightRedistributor
 */
class WeightRedistributorTest extends TestCase
{
    /**
     * Test that a native 3+1 is repacked into 2+2.
     */
    public function testWeightRedistributionActivatesOrNot(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 1, 1, 3, 0, 1, 1, 3, 3));
        $packer->addItem(new TestItem('Item', 1, 1, 1, 1, false), 4);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(2, $packedBoxes[0]->getItems());
        self::assertCount(2, $packedBoxes[1]->getItems());
    }

    /**
     * From issue #166.
     */
    public function testIssue166(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Pallet', 42, 42, 42, 0, 42, 42, 42, 1120));
        $packer->addItem(new ConstrainedPlacementNoStackingTestItem('Item', 8, 7, 7, 36, false), 84);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(3, $packedBoxes);
        self::assertCount(28, $packedBoxes[0]->getItems());
        self::assertCount(28, $packedBoxes[1]->getItems());
        self::assertCount(28, $packedBoxes[2]->getItems());
    }

    public function testWeightDistributionWorks(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 370, 375, 60, 140, 364, 374, 40, 3000));
        $packer->addItem(new TestItem('Item 1', 230, 330, 6, 320, true), 2);
        $packer->addItem(new TestItem('Item 2', 210, 297, 8, 300, true), 4);

        $packedBoxes = $packer->pack();

        self::assertEquals(0, $packedBoxes->getWeightVariance());
    }
}
