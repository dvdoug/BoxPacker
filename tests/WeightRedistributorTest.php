<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\ConstrainedPlacementNoStackingTestItem;
use DVDoug\BoxPacker\Test\LimitedSupplyTestBox;
use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

#[CoversClass(WeightRedistributor::class)]
class WeightRedistributorTest extends TestCase
{
    /**
     * Test that a native 3+1 is repacked into 2+2.
     */
    public function testWeightRedistributionActivatesOrNot(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 1, 1, 3, 0, 1, 1, 3, 3));
        $packer->addItem(new TestItem('Item', 1, 1, 1, 1, Rotation::BestFit), 4);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(2, $packedBoxes[0]->items);
        self::assertCount(2, $packedBoxes[1]->items);
    }

    /**
     * From issue #166.
     */
    #[Group('efficiency')]
    public function testIssue166(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Pallet', 42, 42, 42, 0, 42, 42, 42, 1120));
        $packer->addItem(new ConstrainedPlacementNoStackingTestItem('Item', 8, 7, 7, 36, Rotation::BestFit), 84);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(3, $packedBoxes);
        self::assertCount(28, $packedBoxes[0]->items);
        self::assertCount(28, $packedBoxes[1]->items);
        self::assertCount(28, $packedBoxes[2]->items);
    }

    public function testWeightDistributionWorks(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 370, 375, 60, 140, 364, 374, 40, 3000));
        $packer->addItem(new TestItem('Item 1', 230, 330, 6, 320, Rotation::KeepFlat), 2);
        $packer->addItem(new TestItem('Item 2', 210, 297, 8, 300, Rotation::KeepFlat), 4);

        $packedBoxes = $packer->pack();

        self::assertEquals(0, $packedBoxes->getWeightVariance());
    }

    /**
     * Test to ensure no items are silently dropped during weight redistribution.
     */
    public function testWeightRedistributionDoesNotSilentlyDropItems(): void
    {
        $packer = new Packer();
        $packer->addBox(new LimitedSupplyTestBox('Box', 29, 29, 29, 0, 29, 29, 29, 68, 2));

        $packer->addItem(new TestItem('Item 0', 10, 10, 10, 2, Rotation::BestFit), 2);
        $packer->addItem(new TestItem('Item 1', 10, 10, 10, 3, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 2', 10, 10, 10, 4, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 3', 10, 10, 10, 8, Rotation::BestFit), 5);
        $packer->addItem(new TestItem('Item 4', 10, 10, 10, 18, Rotation::BestFit), 3);

        // packer initially packs 6 items into each box,
        // the imbalance in weights will be attempted to be corrected by the WeightRedistributor
        $packedBoxes = $packer->pack();
        self::assertCount(2, $packedBoxes);

        $packedItemCount = 0;
        foreach ($packedBoxes as $packedBox) {
            $packedItemCount += $packedBox->items->count();
        }

        self::assertSame(
            12,
            $packedItemCount + $packer->getUnpackedItems()->count(),
            'No items should be lost during weight redistribution'
        );
    }
}
