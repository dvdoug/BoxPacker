<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Exception\NoBoxesAvailableException;
use DVDoug\BoxPacker\Test\ConstrainedPlacementByCountTestItem;
use DVDoug\BoxPacker\Test\LimitedSupplyTestBox;
use DVDoug\BoxPacker\Test\PackedBoxByReferenceSorter;
use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;

use function iterator_to_array;

use const PHP_INT_MAX;

use PHPUnit\Framework\TestCase;

class PackerTest extends TestCase
{
    public function testPackThreeItemsOneDoesntFitInAnyBoxWhenThrowing(): void
    {
        $this->expectException(NoBoxesAvailableException::class);
        $box1 = new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000);
        $box2 = new TestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000);

        $item1 = new TestItem('Item 1', 2500, 2500, 20, 2000, Rotation::BestFit);
        $item2 = new TestItem('Item 2', 25000, 2500, 20, 2000, Rotation::BestFit);
        $item3 = new TestItem('Item 3', 2500, 2500, 20, 2000, Rotation::BestFit);

        $packer = new Packer();
        $packer->addBox($box1);
        $packer->addBox($box2);
        $packer->addItem($item1);
        $packer->addItem($item2);
        $packer->addItem($item3);
        $packer->pack();
    }

    public function testPackWithoutBox(): void
    {
        $this->expectException(NoBoxesAvailableException::class);
        $item1 = new TestItem('Item 1', 2500, 2500, 20, 2000, Rotation::BestFit);
        $item2 = new TestItem('Item 2', 25000, 2500, 20, 2000, Rotation::BestFit);
        $item3 = new TestItem('Item 3', 2500, 2500, 20, 2000, Rotation::BestFit);

        $packer = new Packer();
        $packer->addItem($item1);
        $packer->addItem($item2);
        $packer->addItem($item3);
        $packer->pack();
    }

    /**
     * Test weight distribution getter/setter.
     */
    public function testCanSetMaxBoxesToWeightBalance(): void
    {
        $packer = new Packer();
        $packer->setMaxBoxesToBalanceWeight(3);
        self::assertEquals(3, $packer->getMaxBoxesToBalanceWeight());
    }

    /**
     * Test that weight redistribution activates (or not) correctly based on the current limit.
     */
    public function testWeightRedistributionActivatesOrNot(): void
    {
        // first pack normally - expecting 2+2 after balancing

        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 1, 1, 3, 0, 1, 1, 3, 3));
        $packer->addItem(new TestItem('Item', 1, 1, 1, 1, Rotation::BestFit), 4);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(2, $packedBoxes[0]->getItems());
        self::assertCount(2, $packedBoxes[1]->getItems());

        // same items, but with redistribution turned off - expecting 3+1 based on pure fit
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 1, 1, 3, 0, 1, 1, 3, 3));
        $packer->addItem(new TestItem('Item', 1, 1, 1, 1, Rotation::BestFit), 4);
        $packer->setMaxBoxesToBalanceWeight(1);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(3, $packedBoxes[0]->getItems());
        self::assertCount(1, $packedBoxes[1]->getItems());
    }

    /**
     * Test used width calculations on a case where it used to fail.
     */
    public function testIssue52A(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 100, 50, 50, 0, 100, 50, 50, 5000));
        $packer->addItem(new TestItem('Item', 15, 13, 8, 407, Rotation::KeepFlat), 2);
        $packedBoxes = $packer->pack();

        self::assertCount(1, $packedBoxes);
        self::assertEquals(26, $packedBoxes->top()->getUsedWidth());
        self::assertEquals(15, $packedBoxes->top()->getUsedLength());
        self::assertEquals(8, $packedBoxes->top()->getUsedDepth());
    }

    /**
     * Test used width calculations on a case where it used to fail.
     */
    public function testIssue52B(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 370, 375, 60, 140, 364, 374, 40, 3000));
        $packer->addItem(new TestItem('Item 1', 220, 310, 12, 679, Rotation::KeepFlat));
        $packer->addItem(new TestItem('Item 2', 210, 297, 11, 648, Rotation::KeepFlat));
        $packer->addItem(new TestItem('Item 3', 210, 297, 5, 187, Rotation::KeepFlat));
        $packer->addItem(new TestItem('Item 4', 148, 210, 32, 880, Rotation::KeepFlat));
        $packedBoxes = $packer->pack();

        self::assertCount(1, $packedBoxes);
        self::assertEquals(310, $packedBoxes->top()->getUsedWidth());
        self::assertEquals(368, $packedBoxes->top()->getUsedLength());
        self::assertEquals(32, $packedBoxes->top()->getUsedDepth());
    }

    /**
     * Test used width calculations on a case where it used to fail.
     */
    public function testIssue52C(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 230, 300, 240, 160, 230, 300, 240, 15000));
        $packer->addItem(new TestItem('Item 1', 210, 297, 4, 213, Rotation::KeepFlat));
        $packer->addItem(new TestItem('Item 2', 80, 285, 70, 199, Rotation::KeepFlat));
        $packer->addItem(new TestItem('Item 3', 80, 285, 70, 199, Rotation::KeepFlat));

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(1, $packedBoxes);
        self::assertEquals(210, $packedBoxes[0]->getUsedWidth());
        self::assertEquals(297, $packedBoxes[0]->getUsedLength());
        self::assertEquals(74, $packedBoxes[0]->getUsedDepth());
    }

    /**
     * Test case where last item algorithm picks a slightly inefficient box.
     */
    public function testIssue117(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box A', 36, 8, 3, 0, 36, 8, 3, 2));
        $packer->addBox(new TestBox('Box B', 36, 8, 8, 0, 36, 8, 8, 2));
        $packer->addItem(new TestItem('Item 1', 35, 7, 2, 1, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 2', 6, 5, 1, 1, Rotation::BestFit));
        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);
        self::assertCount(1, $packedBoxes);
        self::assertEquals('Box A', $packedBoxes[0]->getBox()->getReference());
    }

    /**
     * Where 2 perfectly filled boxes are a choice, need to ensure we pick the larger one or there is a cascading
     * failure of many small boxes instead of a few larger ones.
     */
    public function testIssue38(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box1', 2, 2, 2, 0, 2, 2, 2, 1000));
        $packer->addBox(new TestBox('Box2', 4, 4, 4, 0, 4, 4, 4, 1000));
        $packer->addItem(new TestItem('Item 1', 1, 1, 1, 100, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 2', 1, 1, 1, 100, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 3', 1, 1, 1, 100, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 4', 1, 1, 1, 100, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 5', 2, 2, 2, 100, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 6', 2, 2, 2, 100, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 7', 2, 2, 2, 100, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 8', 2, 2, 2, 100, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 9', 4, 4, 4, 100, Rotation::BestFit));

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(2, $packedBoxes);
    }

    /**
     * From issue #168.
     */
    public function testIssue168(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Small', 85, 190, 230, 30, 85, 190, 230, 10000));
        $packer->addBox(new TestBox('Medium', 220, 160, 160, 50, 220, 160, 160, 10000));
        $packer->addItem(new TestItem('Item', 55, 85, 122, 350, Rotation::BestFit));

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(1, $packedBoxes);
        self::assertEquals('Small', $packedBoxes[0]->getBox()->getReference());
    }

    /**
     * From issue #182.
     * @group efficiency
     */
    public function testIssue182A(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box 1', 410, 310, 310, 2000, 410, 310, 310, 60000));
        $packer->addBox(new TestBox('Box 2', 410, 310, 260, 2000, 410, 310, 260, 60000));
        $packer->addBox(new TestBox('Box 3', 410, 310, 205, 2000, 410, 310, 205, 60000));
        $packer->addBox(new TestBox('Box 4', 310, 310, 210, 2000, 310, 310, 210, 60000));
        $packer->addBox(new TestBox('Box 5', 310, 210, 210, 2000, 310, 210, 210, 60000));
        $packer->addBox(new TestBox('Box 6', 310, 210, 155, 2000, 310, 210, 155, 60000));
        $packer->addBox(new TestBox('Box 7', 210, 160, 105, 2000, 210, 160, 105, 60000));

        $packer->addItem(new TestItem('Item', 150, 100, 100, 1, Rotation::BestFit), 200);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(9, $packedBoxes);
    }

    /**
     * Test that unlimited supply boxes are handled correctly.
     */
    public function testUnlimitedSupplyBox(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Light box', 100, 100, 100, 1, 100, 100, 100, 100));
        $packer->addBox(new TestBox('Heavy box', 100, 100, 100, 100, 100, 100, 100, 10000));

        $packer->addItem(new TestItem('Item', 100, 100, 100, 75, Rotation::BestFit), 3);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(3, $packedBoxes);
        self::assertEquals('Light box', $packedBoxes[0]->getBox()->getReference());
        self::assertEquals('Light box', $packedBoxes[1]->getBox()->getReference());
        self::assertEquals('Light box', $packedBoxes[2]->getBox()->getReference());
    }

    /**
     * Test that limited supply boxes are handled correctly.
     */
    public function testLimitedSupplyBox(): void
    {
        // as above, but limit light box to quantity 2
        $packer = new Packer();
        $packer->addBox(new LimitedSupplyTestBox('Light box', 100, 100, 100, 1, 100, 100, 100, 100, 2));
        $packer->addBox(new TestBox('Heavy box', 100, 100, 100, 100, 100, 100, 100, 10000));

        $packer->addItem(new TestItem('Item', 100, 100, 100, 75, Rotation::BestFit), 3);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(3, $packedBoxes);
        self::assertEquals('Heavy box', $packedBoxes[0]->getBox()->getReference());
        self::assertEquals('Light box', $packedBoxes[1]->getBox()->getReference());
        self::assertEquals('Light box', $packedBoxes[2]->getBox()->getReference());
    }

    /**
     * Test that limited supply boxes are handled correctly.
     */
    public function testNotEnoughLimitedSupplyBox(): void
    {
        // as above, but remove heavy box as an option
        $this->expectException(NoBoxesAvailableException::class);
        $packer = new Packer();
        $packer->addBox(new LimitedSupplyTestBox('Light box', 100, 100, 100, 1, 100, 100, 100, 100, 2));
        $packer->addItem(new TestItem('Item', 100, 100, 100, 75, Rotation::BestFit), 3);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);
    }

    /**
     * From issue #191.
     */
    public function testIssue191(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('B 1', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 400, 150, 200, 10, Rotation::BestFit), 2);
        self::assertCount(1, $packer->pack());

        $packer = new Packer();
        $packer->addBox(new TestBox('B 1', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 400, 200, 150, 10, Rotation::BestFit), 2);
        self::assertCount(1, $packer->pack());

        $packer = new Packer();
        $packer->addBox(new TestBox('B 1', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 200, 400, 150, 10, Rotation::BestFit), 2);
        self::assertCount(1, $packer->pack());

        $packer = new Packer();
        $packer->addBox(new TestBox('B 1', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 400, 150, 200, 10, Rotation::BestFit), 1);
        $packer->addItem(new TestItem('Item 2', 400, 200, 150, 10, Rotation::BestFit), 1);
        self::assertCount(1, $packer->pack());

        $packer = new Packer();
        $packer->addBox(new TestBox('B 1', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 400, 200, 150, 10, Rotation::BestFit), 1);
        $packer->addItem(new TestItem('Item 2', 400, 150, 200, 10, Rotation::BestFit), 1);
        self::assertCount(1, $packer->pack());
    }

    /**
     * From issue #192.
     */
    public function testIssue192(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 350, 250, 55, 10, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 2', 225, 180, 55, 10, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 3', 265, 195, 30, 10, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 4', 260, 190, 30, 10, Rotation::BestFit));
        self::assertCount(1, $packer->pack());

        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 345, 250, 55, 10, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 2', 225, 180, 55, 10, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 3', 265, 195, 30, 10, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 4', 260, 190, 30, 10, Rotation::BestFit));
        self::assertCount(1, $packer->pack());

        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 350, 250, 55, 10, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 2', 225, 180, 50, 10, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 3', 265, 195, 30, 10, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 4', 260, 190, 30, 10, Rotation::BestFit));
        self::assertCount(1, $packer->pack());

        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 360, 250, 55, 10, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 2', 225, 180, 55, 10, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 3', 265, 195, 30, 10, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 4', 260, 190, 30, 10, Rotation::BestFit));
        self::assertCount(1, $packer->pack());

        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 350, 250, 55, 10, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 2', 225, 180, 60, 10, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 3', 265, 195, 30, 10, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 4', 260, 190, 30, 10, Rotation::BestFit));
        self::assertCount(1, $packer->pack());
    }

    /**
     * From issue #196.
     */
    public function testIssue196(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('[Box]', 360, 620, 370, 1, 360, 620, 370, 29000));
        $packer->addItem(new TestItem('C5 240 x 165 mm, 1000 vnt.', 259, 375, 99, 5440, Rotation::BestFit), 4);
        $packer->addItem(new TestItem('170x230+30mm', 200, 300, 10, 194, Rotation::BestFit), 12);
        $packer->addItem(new TestItem('50 mm x 66 m, ruda', 100, 100, 50, 165, Rotation::BestFit), 12);
        self::assertCount(1, $packer->pack());

        $packer = new Packer();
        $packer->addBox(new TestBox('[Box]', 360, 620, 370, 1, 360, 620, 370, 29000));
        $packer->addItem(new TestItem('C5 240 x 165 mm, 1000 vnt.', 259, 375, 99, 5440, Rotation::BestFit), 4);
        $packer->addItem(new TestItem('170x230+30mm', 200, 300, 10, 194, Rotation::BestFit), 12);
        $packer->addItem(new TestItem('50 mm x 66 m, ruda', 100, 100, 50, 165, Rotation::BestFit), 12);
        $packer->addItem(new TestItem('175 x 255, B5', 130, 210, 30, 391, Rotation::BestFit), 2);
        self::assertCount(1, $packer->pack());
    }

    /**
     * From PR #198, tests with an atypically large number of boxes.
     * @group efficiency
     */
    public function testNumberOfBoxesTorture(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('W10 - Plain Box 24x14x9.5', 1400, 2400, 950, 1000, 1400, 2400, 950, 60000));
        $packer->addBox(new TestBox('Box- 36x24x10', 2400, 3600, 1000, 3700, 2400, 3600, 1000, 60000));
        $packer->addBox(new TestBox('Box- 12x12x12', 1200, 1200, 1200, 1000, 1200, 1200, 1200, 60000));
        $packer->addBox(new TestBox('Z4 - 28x22x12', 2200, 2800, 1200, 2000, 2200, 2800, 1200, 60000));
        $packer->addBox(new TestBox('Z3 - 33x14x12', 1400, 3300, 1200, 2000, 1400, 3300, 1200, 60000));
        $packer->addBox(new TestBox('Z2 - 22x14x12', 1400, 2200, 1200, 2000, 1400, 2200, 1200, 60000));
        $packer->addBox(new TestBox('PB2 - Plain Box 80x3x3 (FOL)', 300, 8000, 300, 2000, 300, 8000, 300, 60000));
        $packer->addBox(new TestBox('PB3 - Plain Box 6x4x80 (FOL)', 400, 600, 8000, 2000, 400, 600, 8000, 60000));
        $packer->addBox(new TestBox('Box 1678 - 44 x 17 x 8', 1700, 4400, 800, 3600, 1700, 4400, 800, 60000));
        $packer->addBox(new TestBox('Box 1677 - 44 x 17 x 6', 1700, 4400, 600, 3400, 1700, 4400, 600, 60000));
        $packer->addBox(new TestBox('Box 1671 - 30 x 4 x 4', 400, 3000, 400, 400, 400, 3000, 400, 60000));
        $packer->addBox(new TestBox('Box 1645 - 84 x 9 x 8', 900, 8400, 800, 2300, 900, 8400, 800, 60000));
        $packer->addBox(new TestBox('Box 1644 - 84 x 9 x 6', 900, 8400, 600, 2200, 900, 8400, 600, 60000));
        $packer->addBox(new TestBox('Box 1642 - 84 x 6 x 6', 600, 8400, 600, 1800, 600, 8400, 600, 60000));
        $packer->addBox(new TestBox('Box 1634 - 80 x 4 x 4', 400, 8000, 400, 1200, 400, 8000, 400, 60000));
        $packer->addBox(new TestBox('Box 1630 - 72 x 9 x 4', 900, 7200, 400, 1600, 900, 7200, 400, 60000));
        $packer->addBox(new TestBox('Box 1626 - 72 x 4 x 4', 400, 7200, 400, 1100, 400, 7200, 400, 60000));
        $packer->addBox(new TestBox('Box 1608 - 60 x 16 x 12', 1600, 6000, 1200, 4600, 1600, 6000, 1200, 60000));
        $packer->addBox(new TestBox('Box 1571 - 48 x 24 x 12', 2400, 4800, 1200, 3600, 2400, 4800, 1200, 60000));
        $packer->addBox(new TestBox('Box 1566 - 48 x 16 x 12', 1600, 4800, 1200, 3900, 1600, 4800, 1200, 60000));
        $packer->addBox(new TestBox('Box 1565 - 48 x 12 x 12', 1200, 4800, 1200, 2500, 1200, 4800, 1200, 60000));
        $packer->addBox(new TestBox('Box 1564 - 48 x 12 x 4', 1200, 4800, 400, 1400, 1200, 4800, 400, 60000));
        $packer->addBox(new TestBox('Box 1562 - 48 x 9 x 4', 900, 4800, 400, 1100, 900, 4800, 400, 60000));
        $packer->addBox(new TestBox('Box 1553 - 44 x 24 x 12', 2400, 4400, 1200, 3400, 2400, 4400, 1200, 60000));
        $packer->addBox(new TestBox('Box 1529 - 42 x 15 x 4', 1500, 4200, 400, 1300, 1500, 4200, 400, 60000));
        $packer->addBox(new TestBox('Box 1527 - 42 x 12 x 4', 1200, 4200, 400, 1100, 1200, 4200, 400, 60000));
        $packer->addBox(new TestBox('Box 1525 - 42 x 9 x 4', 900, 4200, 400, 900, 900, 4200, 400, 60000));
        $packer->addBox(new TestBox('Box 1523 - 42 x 6 x 4', 600, 4200, 400, 700, 600, 4200, 400, 60000));
        $packer->addBox(new TestBox('Box 1521 - 42 x 4 x 4', 400, 4200, 400, 600, 400, 4200, 400, 60000));
        $packer->addBox(new TestBox('Box 1475 - 39 x 8 x 6', 800, 3900, 600, 900, 800, 3900, 600, 60000));
        $packer->addBox(new TestBox('Box 1469 - 36 x 36 x 11', 3600, 3600, 1100, 3100, 3600, 3600, 1100, 60000));
        $packer->addBox(new TestBox('Box 1467 - 36 x 36 x 4', 3600, 3600, 400, 2500, 3600, 3600, 400, 60000));
        $packer->addBox(new TestBox('Box 1466 - 36 x 36 x 3', 3600, 3600, 300, 2300, 3600, 3600, 300, 60000));
        $packer->addBox(new TestBox('Box 1457 - 36 x 30 x 9', 3000, 3600, 900, 2500, 3000, 3600, 900, 60000));
        $packer->addBox(new TestBox('Box 1444 - 36 x 24 x 9', 2400, 3600, 900, 2100, 2400, 3600, 900, 60000));
        $packer->addBox(new TestBox('Box 1443 - 36 x 24 x 6', 2400, 3600, 600, 2000, 2400, 3600, 600, 60000));
        $packer->addBox(new TestBox('Box 1442 - 36 x 24 x 4', 2400, 3600, 400, 1700, 2400, 3600, 400, 60000));
        $packer->addBox(new TestBox('Box 1441 - 36 x 24 x 3', 2400, 3600, 300, 1600, 2400, 3600, 300, 60000));
        $packer->addBox(new TestBox('Box 1432 - 36 x 20 x 14', 2000, 3600, 1400, 2700, 2000, 3600, 1400, 60000));
        $packer->addBox(new TestBox('Box 1431 - 36 x 20 x 12', 2000, 3600, 1200, 2500, 2000, 3600, 1200, 60000));
        $packer->addBox(new TestBox('Box 1421 - 36 x 16 x 16', 1600, 3600, 1600, 2300, 1600, 3600, 1600, 60000));
        $packer->addBox(new TestBox('Box 1419 - 36 x 16 x 12', 1600, 3600, 1200, 3200, 1600, 3600, 1200, 60000));
        $packer->addBox(new TestBox('Box 1412 - 36 x 14 x 12', 1400, 3600, 1200, 2800, 1400, 3600, 1200, 60000));
        $packer->addBox(new TestBox('Box 1392 - 36 x 6 x 6', 600, 3600, 600, 900, 600, 3600, 600, 60000));
        $packer->addBox(new TestBox('Box 1391 - 36 x 6 x 4', 600, 3600, 400, 700, 600, 3600, 400, 60000));
        $packer->addBox(new TestBox('Box 1328 - 33 x 20 x 14', 2000, 3300, 1400, 2200, 2000, 3300, 1400, 60000));
        $packer->addBox(new TestBox('Box 1291 - 32 x 12 x 8', 1200, 3200, 800, 1400, 1200, 3200, 800, 60000));
        $packer->addBox(new TestBox('Box 1281 - 32 x 6 x 6', 600, 3200, 600, 800, 600, 3200, 600, 60000));
        $packer->addBox(new TestBox('Box 1213 - 30 x 28 x 18', 2800, 3000, 1800, 3700, 2800, 3000, 1800, 60000));
        $packer->addBox(new TestBox('Box 1171 - 30 x 20 x 18', 2000, 3000, 1800, 2700, 2000, 3000, 1800, 60000));
        $packer->addBox(new TestBox('Box 1159 - 30 x 18 x 10', 1800, 3000, 1000, 1900, 1800, 3000, 1000, 60000));
        $packer->addBox(new TestBox('Box 1131 - 30 x 6 x 4', 600, 3000, 400, 500, 600, 3000, 400, 60000));
        $packer->addBox(new TestBox('Box 1126 - 28 x 28 x 12', 2800, 2800, 1200, 2200, 2800, 2800, 1200, 60000));
        $packer->addBox(new TestBox('Box 1125 - 28 x 28 x 10', 2800, 2800, 1000, 2600, 2800, 2800, 1000, 60000));
        $packer->addBox(new TestBox('Box 1108 - 28 x 24 x 14', 2400, 2800, 1400, 2200, 2400, 2800, 1400, 60000));
        $packer->addBox(new TestBox('Box 1091 - 28 x 20 x 18', 2000, 2800, 1800, 2500, 2000, 2800, 1800, 60000));
        $packer->addBox(new TestBox('Box 1081 - 28 x 18 x 14', 1800, 2800, 1400, 2100, 1800, 2800, 1400, 60000));
        $packer->addBox(new TestBox('Box 1031 - 26 x 22 x 10', 2200, 2600, 1000, 2200, 2200, 2600, 1000, 60000));
        $packer->addBox(new TestBox('Box 1025 - 26 x 20 x 15', 2000, 2600, 1500, 2300, 2000, 2600, 1500, 60000));
        $packer->addBox(new TestBox('Box 1023 - 26 x 20 x 12', 2000, 2600, 1200, 2100, 2000, 2600, 1200, 60000));
        $packer->addBox(new TestBox('Box 1019 - 26 x 20 x 4', 2000, 2600, 400, 1600, 2000, 2600, 400, 60000));
        $packer->addBox(new TestBox('Box 1000 - 26 x 14 x 8', 1400, 2600, 800, 1300, 1400, 2600, 800, 60000));
        $packer->addBox(new TestBox('Box 998 - 26 x 14 x 4', 1400, 2600, 400, 1000, 1400, 2600, 400, 60000));
        $packer->addBox(new TestBox('Box 979 - 24 x 24 x 16', 2400, 2400, 1600, 2200, 2400, 2400, 1600, 60000));
        $packer->addBox(new TestBox('Box 974 - 24 x 24 x 6', 2400, 2400, 600, 2000, 2400, 2400, 600, 60000));
        $packer->addBox(new TestBox('Box 938 - 24 x 14 x 12', 1400, 2400, 1200, 1400, 1400, 2400, 1200, 60000));
        $packer->addBox(new TestBox('Box 919 - 22 x 22 x 22', 2200, 2200, 2200, 2700, 2200, 2200, 2200, 60000));
        $packer->addBox(new TestBox('Box 911 - 22 x 22 x 6', 2200, 2200, 600, 1700, 2200, 2200, 600, 60000));
        $packer->addBox(new TestBox('Box 866 - 22 x 6 x 4', 600, 2200, 400, 400, 600, 2200, 400, 60000));
        $packer->addBox(new TestBox('Box 864 - 20 x 20 x 8', 2000, 2000, 800, 1600, 2000, 2000, 800, 60000));
        $packer->addBox(new TestBox('Box 769 - 20 x 10 x 9', 1000, 2000, 900, 800, 1000, 2000, 900, 60000));
        $packer->addBox(new TestBox('Box 640 - 19 x 8 x 7', 800, 1900, 700, 600, 800, 1900, 700, 60000));
        $packer->addBox(new TestBox('Box 619 - 18 x 18 x 7', 1800, 1800, 700, 1300, 1800, 1800, 700, 60000));
        $packer->addBox(new TestBox('Box 597 - 18 x 16 x 12', 1600, 1800, 1200, 1400, 1600, 1800, 1200, 60000));
        $packer->addBox(new TestBox('Box 553 - 18 x 12 x 10', 1200, 1800, 1000, 900, 1200, 1800, 1000, 60000));
        $packer->addBox(new TestBox('Box 533 - 18 x 10 x 5', 1000, 1800, 500, 600, 1000, 1800, 500, 60000));
        $packer->addBox(new TestBox('Box 468 - 17 x 14 x 7', 1400, 1700, 700, 900, 1400, 1700, 700, 60000));
        $packer->addBox(new TestBox('Box 407 - 16 x 16 x 10', 1600, 1600, 1000, 1200, 1600, 1600, 1000, 60000));
        $packer->addBox(new TestBox('Box 359 - 16 x 12 x 4', 1200, 1600, 400, 700, 1200, 1600, 400, 60000));
        $packer->addBox(new TestBox('Box 336 - 16 x 8 x 7', 800, 1600, 700, 500, 800, 1600, 700, 60000));
        $packer->addBox(new TestBox('Box 242 - 14 x 14 x 4', 1400, 1400, 400, 700, 1400, 1400, 400, 60000));
        $packer->addBox(new TestBox('Box 168 - 13 x 11 x 8', 1100, 1300, 800, 700, 1100, 1300, 800, 60000));
        $packer->addBox(new TestBox('Box 140 - 13 x 6 x 5', 600, 1300, 500, 300, 600, 1300, 500, 60000));
        $packer->addBox(new TestBox('Box 80 - 11 x 9 x 6', 900, 1100, 600, 400, 900, 1100, 600, 60000));
        $packer->addBox(new TestBox('Box 59 - 10 x 10 x 4', 1000, 1000, 400, 400, 1000, 1000, 400, 60000));
        $packer->addBox(new TestBox('Box 55 - 10 x 9 x 6', 900, 1000, 600, 400, 900, 1000, 600, 60000));
        $packer->addBox(new TestBox('Box 53 - 10 x 9 x 4', 900, 1000, 400, 400, 900, 1000, 400, 60000));
        $packer->addBox(new TestBox('Box 51 - 10 x 8 x 7', 800, 1000, 700, 400, 800, 1000, 700, 60000));
        $packer->addBox(new TestBox('Box 48 - 10 x 8 x 4', 800, 1000, 400, 300, 800, 1000, 400, 60000));
        $packer->addBox(new TestBox('Box 44 - 10 x 7 x 4', 700, 1000, 400, 300, 700, 1000, 400, 60000));
        $packer->addBox(new TestBox('Box 43 - 10 x 6 x 6', 600, 1000, 600, 300, 600, 1000, 600, 60000));
        $packer->addBox(new TestBox('Box 33 - 9 x 8 x 7', 800, 900, 700, 400, 800, 900, 700, 60000));
        $packer->addBox(new TestBox('Box 26 - 9 x 7 x 4', 700, 900, 400, 300, 700, 900, 400, 60000));
        $packer->addBox(new TestBox('Box 18 - 8 x 8 x 4', 800, 800, 400, 300, 800, 800, 400, 60000));
        $packer->addBox(new TestBox('Box 15 - 8 x 7 x 5', 700, 800, 500, 300, 700, 800, 500, 60000));
        $packer->addBox(new TestBox('Box 12 - 8 x 6 x 5', 600, 800, 500, 200, 600, 800, 500, 60000));
        $packer->addBox(new TestBox('Box 9 - 7 x 7 x 6', 700, 700, 600, 300, 700, 700, 600, 60000));
        $packer->addBox(new TestBox('Box 8 - 7 x 7 x 5', 700, 700, 500, 300, 700, 700, 500, 60000));
        $packer->addBox(new TestBox('Box 6 - 7 x 6 x 6', 600, 700, 600, 200, 600, 700, 600, 60000));
        $packer->addBox(new TestBox('Box 5 - 7 x 6 x 5', 600, 700, 500, 200, 600, 700, 500, 60000));
        $packer->addBox(new TestBox('Box 4 - 7 x 6 x 4', 600, 700, 400, 200, 600, 700, 400, 60000));
        $packer->addBox(new TestBox('Box 3 - 6 x 6 x 6', 600, 600, 600, 200, 600, 600, 600, 60000));
        $packer->addBox(new TestBox('Box 2 - 6 x 6 x 5', 600, 600, 500, 200, 600, 600, 500, 60000));
        $packer->addBox(new TestBox('Box 1 - 6 x 6 x 4', 600, 600, 400, 200, 600, 600, 400, 60000));
        $packer->addBox(new TestBox('PB1 - Plain box 22x12x6', 1200, 2200, 600, 850, 1200, 2200, 600, 60000));
        $packer->addBox(new TestBox('W9 - Plain Box 16x12x8', 1200, 1600, 800, 952, 1200, 1600, 800, 60000));
        $packer->addBox(new TestBox('W8 - Plain Box  14x11x11', 1100, 1400, 1100, 1000, 1100, 1400, 1100, 60000));
        $packer->addBox(new TestBox('W7 - Plain Box 8x5x3.5', 500, 800, 350, 200, 500, 800, 350, 60000));
        $packer->addBox(new TestBox('W6 - Plain Box 22.5x16x10.5', 1600, 2250, 1050, 2140, 1600, 2250, 1050, 60000));
        $packer->addBox(new TestBox('W5 - Plain Box 16x10.5x15.25', 1050, 1600, 1525, 1200, 1050, 1600, 1525, 60000));
        $packer->addBox(new TestBox('W3 - Plain Box 12x12x16', 1200, 1200, 1600, 1300, 1200, 1200, 1600, 60000));
        $packer->addBox(new TestBox('W1 - Plain Box 11.5x9.5x3.5', 950, 1150, 350, 420, 950, 1150, 350, 60000));
        $packer->addBox(new TestBox('W4 - Plain Box 16x8x8', 800, 1600, 800, 614, 800, 1600, 800, 60000));
        $packer->addBox(new TestBox('W2 - Plain Box 12x6x6', 600, 1200, 600, 500, 600, 1200, 600, 60000));
        $packer->addBox(new TestBox('A18 - LQ 5x5x8', 575, 575, 825, 300, 575, 575, 825, 60000));
        $packer->addBox(new TestBox('A17 - LQ 8x5x8', 575, 825, 825, 350, 575, 825, 825, 60000));
        $packer->addBox(new TestBox('A16 - LQ 13x13x13', 1300, 1300, 1300, 1200, 1300, 1300, 1300, 60000));
        $packer->addBox(new TestBox('Box 1683', 1400, 2200, 1600, 2246, 1400, 2200, 1600, 60000));
        $packer->addBox(new TestBox('Box 1684', 800, 2800, 1800, 1382, 800, 2800, 1800, 60000));
        $packer->addBox(new TestBox('Box 1685- 26x15x20', 1500, 2600, 2000, 2570, 1500, 2600, 2000, 60000));
        $packer->addBox(new TestBox('Box 1686- 14x10x36', 1000, 1400, 3600, 2222, 1000, 1400, 3600, 60000));
        $packer->addBox(new TestBox('Box 1682 - 22x6x15', 600, 2200, 1500, 1048, 600, 2200, 1500, 60000));
        $packer->addBox(new TestBox('Box 1681', 1600, 1600, 1600, 1750, 1600, 1600, 1600, 60000));
        $packer->addBox(new TestBox('A12 - LQ 28x12x9', 1200, 2800, 900, 1400, 1200, 2800, 900, 60000));
        $packer->addBox(new TestBox('A11 - LQ 22x10x6', 1000, 2200, 600, 900, 1000, 2200, 600, 60000));
        $packer->addBox(new TestBox('A10 - LQ 21x15x6', 1500, 2100, 600, 1450, 1500, 2100, 600, 60000));
        $packer->addBox(new TestBox('A9 - LQ 18x12x10', 1200, 1800, 1000, 1350, 1200, 1800, 1000, 60000));
        $packer->addBox(new TestBox('A8 - LQ 16x12x8', 1200, 1600, 800, 950, 1200, 1600, 800, 60000));
        $packer->addBox(new TestBox('A7 - LQ 16x8x8', 800, 1600, 800, 800, 800, 1600, 800, 60000));
        $packer->addBox(new TestBox('A6 - LQ 15x6.5x4', 650, 1500, 400, 400, 650, 1500, 400, 60000));
        $packer->addBox(new TestBox('A5 - LQ 14x11x11', 1100, 1400, 1100, 950, 1100, 1400, 1100, 60000));
        $packer->addBox(new TestBox('A4 - LQ 13x9x7', 900, 1300, 700, 650, 900, 1300, 700, 60000));
        $packer->addBox(new TestBox('A3 - LQ 12x12x8', 1200, 1200, 800, 900, 1200, 1200, 800, 60000));
        $packer->addBox(new TestBox('A2 - LQ 10x8x6', 800, 1000, 600, 450, 800, 1000, 600, 60000));
        $packer->addBox(new TestBox('A1 - LQ 8x5x3.5', 600, 800, 400, 200, 600, 800, 400, 60000));

        $packer->addItem(new TestItem('Item 1', 725, 1500, 650, 21403, Rotation::BestFit), 1);
        $packer->addItem(new TestItem('Item 2', 562, 731, 568, 6994, Rotation::BestFit), 6);
        $packer->addItem(new TestItem('Item 3', 612, 1475, 650, 25008, Rotation::BestFit), 3);
        $packer->addItem(new TestItem('Item 4', 600, 1225, 650, 13210, Rotation::BestFit), 2);
        $packer->addItem(new TestItem('Item 5', 975, 1200, 500, 18734, Rotation::BestFit), 4);
        $packer->addItem(new TestItem('Item 6', 550, 1495, 550, 14160, Rotation::BestFit), 2);
        $packer->addItem(new TestItem('Item 7', 275, 475, 175, 944, Rotation::BestFit), 1);
        $packer->addItem(new TestItem('Item 8', 625, 1000, 650, 14021, Rotation::BestFit), 1);
        $packer->addItem(new TestItem('Item 9', 612, 650, 637, 5661, Rotation::BestFit), 1);
        $packer->addItem(new TestItem('Item 10', 287, 612, 125, 514, Rotation::BestFit), 2);

        $packedBoxList = $packer->pack();
        self::assertCount(6, $packedBoxList);
    }

    public function testIssue206(): void
    {
        ConstrainedPlacementByCountTestItem::$limit = 2;
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 6, 10, 6, 0, 6, 10, 5, PHP_INT_MAX));
        $packer->addItem(new ConstrainedPlacementByCountTestItem('ConstrainedItem', 1, 1, 1, 1, Rotation::BestFit), 3);
        $packer->addItem(new TestItem('RegularItem', 2, 4, 1, 2, Rotation::BestFit), 5);
        $packedBoxes = $packer->pack();

        self::assertCount(2, $packedBoxes);
    }

    public function testIssue231(): void
    {
        $packer = new Packer();
        $packer->setMaxBoxesToBalanceWeight(0);
        $packer->addBox(new TestBox('Box 2.5-1', 30, 20, 20, 2, 30, 20, 20, 1000));

        $itemList = new ItemList();
        $itemList->insert(new TestItem('Item 1', 20, 20, 2, 0, Rotation::BestFit), 4);
        $itemList->insert(new TestItem('Item 2', 8, 3, 2, 0, Rotation::BestFit), 5);
        $itemList->insert(new TestItem('Item 3', 10, 10, 10, 0, Rotation::BestFit), 4);
        $itemList->insert(new TestItem('Item 4', 12, 12, 10, 0, Rotation::BestFit), 2);
        $itemList->insert(new TestItem('Item 5', 6, 4, 2, 0, Rotation::BestFit), 2);
        $packer->setItems($itemList);
        $packedBoxes = $packer->pack();

        self::assertCount(1, $packedBoxes);
    }

    public function testIssue244(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('11', 4400, 1400, 3400, 0, 4600, 1600, 3600, 30000));
        $packer->addItem(new TestItem('Shakes', 900, 95, 1500, 34, Rotation::BestFit), 6);
        $packer->addItem(new TestItem('Bars', 356, 170, 1056, 56, Rotation::BestFit), 6);
        $packer->addItem(new TestItem('Noodles', 1250, 140, 1650, 45, Rotation::BestFit), 6);
        $packer->addItem(new TestItem('Ready Meals', 1250, 285, 1600, 270, Rotation::BestFit), 6);
        $packer->addItem(new TestItem('Ready Meals', 1250, 285, 1600, 270, Rotation::BestFit), 6);
        $packer->addItem(new TestItem('Ready Meals', 1250, 285, 1600, 270, Rotation::BestFit), 3);
        $packer->addItem(new TestItem('Ready Meals', 1250, 285, 1600, 270, Rotation::BestFit), 4);
        $packer->addItem(new TestItem('Soups', 1000, 60, 1400, 35, Rotation::BestFit), 2);
        $packer->addItem(new TestItem('Cereals', 850, 60, 1400, 40, Rotation::BestFit), 3);
        $packer->addItem(new TestItem('Snacks', 1600, 300, 2000, 30, Rotation::BestFit), 1);

        $packedBoxes = $packer->pack();

        self::assertCount(1, $packedBoxes);
    }

    public function testIssue248(): void
    {
        $items = [];
        $packer = new Packer();
        $packer->addBox(new LimitedSupplyTestBox('FULL_SLAB', 3150, 1520, 1, 1, 3150, 1520, 1, 10000000, 1000));

        $items[] = new TestItem('Item 1', 1900, 50, 1, 1, Rotation::BestFit);
        $items[] = new TestItem('Item 2', 700, 50, 1, 1, Rotation::BestFit);
        $items[] = new TestItem('Item 3', 600, 50, 1, 1, Rotation::BestFit);
        $items[] = new TestItem('Item 4', 1300, 50, 1, 1, Rotation::BestFit);
        $items[] = new TestItem('Item 5', 1200, 200, 1, 1, Rotation::BestFit);
        $items[] = new TestItem('Item 6', 2500, 50, 1, 1, Rotation::BestFit);
        $items[] = new TestItem('Item 7', 900, 600, 1, 1, Rotation::BestFit);
        $items[] = new TestItem('Item 8', 1900, 600, 1, 1, Rotation::BestFit);
        $items[] = new TestItem('Item 9', 1300, 600, 1, 1, Rotation::BestFit);
        $items[] = new TestItem('Item 10', 1900, 600, 1, 1, Rotation::BestFit);
        $items[] = new TestItem('Item 11', 1500, 600, 1, 1, Rotation::BestFit);

        $packer->setItems(ItemList::fromArray($items, true));
        $packedBoxes = $packer->pack();

        self::assertCount(2, $packedBoxes);
    }

    public function testPackThreeItemsOneDoesntFitInAnyBoxWhenNotThrowing(): void
    {
        $box1 = new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000);
        $box2 = new TestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000);

        $item1 = new TestItem('Item 1', 2500, 2500, 20, 2000, Rotation::BestFit);
        $item2 = new TestItem('Item 2', 25000, 2500, 20, 2000, Rotation::BestFit);
        $item3 = new TestItem('Item 3', 2500, 2500, 20, 2000, Rotation::BestFit);

        $packer = new Packer();
        $packer->throwOnUnpackableItem(false);
        $packer->addBox($box1);
        $packer->addBox($box2);
        $packer->addItem($item1);
        $packer->addItem($item2);
        $packer->addItem($item3);

        $packedBoxes = $packer->pack();

        self::assertCount(1, $packedBoxes);
        self::assertCount(2, $packedBoxes->top()->getItems());
        self::assertCount(1, $packer->getUnpackedItems());
    }

    public function testTooLargeItemsHandledWhenNotThrowing(): void
    {
        $packer = new Packer();
        $packer->throwOnUnpackableItem(false);
        $packer->addBox(new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000));
        $packer->addBox(new TestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000));
        $packer->addItem(new TestItem('Item 1', 2500, 2500, 20, 2000, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 2', 25000, 2500, 20, 2000, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 3', 2500, 2500, 20, 2000, Rotation::BestFit));

        $packedBoxes = $packer->pack();

        self::assertCount(1, $packedBoxes);
        self::assertCount(2, $packedBoxes->top()->getItems());
        self::assertCount(1, $packer->getUnpackedItems());
    }

    public function testUnpackableItemsHandledWhenNotThrowing(): void
    {
        $packer = new Packer();
        $packer->throwOnUnpackableItem(false);
        $packer->addBox(new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000));
        $packer->addBox(new LimitedSupplyTestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000, 0));
        $packer->addItem(new TestItem('Item 1', 2500, 2500, 20, 2000, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 2', 25000, 2500, 20, 2000, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 3', 2500, 2500, 20, 2000, Rotation::BestFit));

        $packedBoxes = $packer->pack();

        self::assertCount(0, $packedBoxes);
        self::assertCount(3, $packer->getUnpackedItems());
    }

    /**
     * From issue #182.
     * @group efficiency
     */
    public function testIssue182B(): void
    {
        $packer = new Packer();
        $packer->throwOnUnpackableItem(false);

        $packer->addBox(new TestBox('1', 225, 283, 165, 249, 206, 259, 151, 15876));
        $packer->addBox(new TestBox('2', 320, 368, 251, 363, 295, 339, 231, 15876));
        $packer->addBox(new TestBox('3', 206, 460, 105, 227, 189, 425, 95, 15876));
        $packer->addBox(new TestBox('4', 470, 473, 327, 658, 434, 437, 301, 15876));
        $packer->addBox(new TestBox('5', 333, 613, 156, 476, 307, 567, 141, 15876));
        $packer->addBox(new TestBox('6', 333, 613, 308, 567, 307, 567, 284, 15876));
        $packer->addBox(new TestBox('7', 473, 692, 378, 1089, 437, 641, 349, 15876));

        $packer->addItem(new TestItem('1', 191, 381, 203, 4536, Rotation::BestFit));
        $packer->addItem(new TestItem('2', 191, 203, 368, 3992, Rotation::BestFit));
        $packer->addItem(new TestItem('3', 191, 203, 368, 3992, Rotation::BestFit));
        $packer->addItem(new TestItem('4', 191, 203, 368, 3992, Rotation::BestFit));
        $packer->addItem(new TestItem('5', 191, 203, 368, 3992, Rotation::BestFit));
        $packer->addItem(new TestItem('6', 191, 203, 368, 3992, Rotation::BestFit));
        $packer->addItem(new TestItem('7', 457, 610, 381, 8165, Rotation::BestFit));
        $packer->addItem(new TestItem('8', 191, 381, 203, 4536, Rotation::BestFit));
        $packer->addItem(new TestItem('9', 191, 203, 368, 3992, Rotation::BestFit));
        $packer->addItem(new TestItem('10', 191, 203, 368, 3992, Rotation::BestFit));
        $packer->addItem(new TestItem('11', 191, 203, 368, 3992, Rotation::BestFit));
        $packer->addItem(new TestItem('12', 191, 203, 368, 3992, Rotation::BestFit));
        $packer->addItem(new TestItem('13', 191, 203, 368, 3992, Rotation::BestFit));
        $packer->addItem(new TestItem('14', 457, 610, 381, 8165, Rotation::BestFit));
        $packer->addItem(new TestItem('15', 368, 419, 533, 12909, Rotation::BestFit));
        $packer->addItem(new TestItem('16', 368, 419, 533, 12909, Rotation::BestFit));
        $packer->addItem(new TestItem('17', 368, 419, 533, 12909, Rotation::BestFit));
        $packer->addItem(new TestItem('18', 368, 419, 533, 12909, Rotation::BestFit));
        $packer->addItem(new TestItem('19', 419, 457, 483, 14751, Rotation::BestFit));
        $packer->addItem(new TestItem('20', 419, 457, 483, 14751, Rotation::BestFit));
        $packer->addItem(new TestItem('21', 432, 572, 178, 6749, Rotation::BestFit));
        $packer->addItem(new TestItem('22', 432, 572, 178, 6749, Rotation::BestFit));
        $packer->addItem(new TestItem('23', 419, 559, 165, 9770, Rotation::BestFit));
        $packer->addItem(new TestItem('24', 419, 559, 165, 9770, Rotation::BestFit));
        $packer->addItem(new TestItem('25', 361, 361, 165, 5330, Rotation::BestFit));
        $packer->addItem(new TestItem('26', 361, 361, 165, 5330, Rotation::BestFit));
        $packer->addItem(new TestItem('27', 381, 483, 152, 3738, Rotation::BestFit));
        $packer->addItem(new TestItem('28', 152, 305, 51, 726, Rotation::BestFit));
        $packer->addItem(new TestItem('29', 318, 406, 102, 2631, Rotation::BestFit));
        $packer->addItem(new TestItem('30', 254, 279, 102, 1479, Rotation::BestFit));
        $packer->addItem(new TestItem('31', 254, 279, 102, 1479, Rotation::BestFit));
        $packer->addItem(new TestItem('32', 133, 248, 76, 526, Rotation::BestFit));
        $packer->addItem(new TestItem('33', 133, 248, 76, 526, Rotation::BestFit));
        $packer->addItem(new TestItem('34', 133, 248, 76, 526, Rotation::BestFit));
        $packer->addItem(new TestItem('35', 133, 248, 76, 526, Rotation::BestFit));
        $packer->addItem(new TestItem('36', 173, 305, 91, 1451, Rotation::BestFit));
        $packer->addItem(new TestItem('37', 203, 381, 140, 2087, Rotation::BestFit));
        $packer->addItem(new TestItem('38', 191, 318, 140, 1225, Rotation::BestFit));
        $packer->addItem(new TestItem('39', 140, 356, 76, 962, Rotation::BestFit));
        $packer->addItem(new TestItem('40', 140, 356, 76, 962, Rotation::BestFit));
        $packer->addItem(new TestItem('41', 137, 356, 69, 816, Rotation::BestFit));
        $packer->addItem(new TestItem('42', 137, 356, 69, 816, Rotation::BestFit));
        $packer->addItem(new TestItem('43', 137, 356, 69, 816, Rotation::BestFit));
        $packer->addItem(new TestItem('44', 381, 467, 89, 3266, Rotation::BestFit));
        $packer->addItem(new TestItem('45', 241, 305, 66, 1089, Rotation::BestFit));
        $packer->addItem(new TestItem('46', 178, 335, 119, 1179, Rotation::BestFit));
        $packer->addItem(new TestItem('47', 178, 335, 119, 1179, Rotation::BestFit));
        $packer->addItem(new TestItem('48', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('49', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('50', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('51', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('52', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('53', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('54', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('55', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('56', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('57', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('58', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('59', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('60', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('61', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('62', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('63', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('64', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('65', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('66', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('67', 229, 254, 127, 839, Rotation::BestFit));
        $packer->addItem(new TestItem('68', 254, 305, 102, 1733, Rotation::BestFit));
        $packer->addItem(new TestItem('69', 254, 305, 102, 1733, Rotation::BestFit));
        $packer->addItem(new TestItem('70', 254, 305, 102, 1733, Rotation::BestFit));
        $packer->addItem(new TestItem('71', 254, 305, 102, 1733, Rotation::BestFit));
        $packer->addItem(new TestItem('72', 254, 305, 102, 1733, Rotation::BestFit));
        $packer->addItem(new TestItem('73', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('74', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('75', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('76', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('77', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('78', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('79', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('80', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('81', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('82', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('83', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('84', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('85', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('86', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('87', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('88', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('89', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('90', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('91', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('92', 184, 375, 108, 1461, Rotation::BestFit));
        $packer->addItem(new TestItem('93', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('94', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('95', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('96', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('97', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('98', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('99', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('100', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('101', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('102', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('103', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('104', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('105', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('106', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('107', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('108', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('109', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('110', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('111', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('112', 152, 229, 57, 871, Rotation::BestFit));
        $packer->addItem(new TestItem('113', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('114', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('115', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('116', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('117', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('118', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('119', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('120', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('121', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('122', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('123', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('124', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('125', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('126', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('127', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('128', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('129', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('130', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('131', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('132', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('133', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('134', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('135', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('136', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('137', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('138', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('139', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('140', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('141', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('142', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('143', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('144', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('145', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('146', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('147', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('148', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('149', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('150', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('151', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('152', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('153', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('154', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('155', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('156', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('157', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('158', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('159', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('160', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('161', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('162', 178, 178, 25, 363, Rotation::BestFit));
        $packer->addItem(new TestItem('163', 140, 241, 25, 272, Rotation::BestFit));
        $packer->addItem(new TestItem('164', 140, 241, 25, 272, Rotation::BestFit));
        $packer->addItem(new TestItem('165', 140, 241, 25, 272, Rotation::BestFit));
        $packer->addItem(new TestItem('166', 140, 241, 25, 272, Rotation::BestFit));
        $packer->addItem(new TestItem('167', 140, 241, 25, 272, Rotation::BestFit));
        $packer->addItem(new TestItem('168', 140, 241, 25, 272, Rotation::BestFit));
        $packer->addItem(new TestItem('169', 140, 241, 25, 272, Rotation::BestFit));
        $packer->addItem(new TestItem('170', 140, 241, 25, 272, Rotation::BestFit));
        $packer->addItem(new TestItem('171', 140, 241, 25, 272, Rotation::BestFit));
        $packer->addItem(new TestItem('172', 140, 241, 25, 272, Rotation::BestFit));
        $packer->addItem(new TestItem('173', 457, 457, 178, 6123, Rotation::BestFit));
        $packer->addItem(new TestItem('174', 457, 457, 178, 6123, Rotation::BestFit));
        $packer->addItem(new TestItem('175', 457, 457, 178, 6123, Rotation::BestFit));
        $packer->addItem(new TestItem('176', 267, 305, 76, 2921, Rotation::BestFit));
        $packer->addItem(new TestItem('177', 267, 305, 76, 2921, Rotation::BestFit));
        $packer->addItem(new TestItem('178', 267, 305, 76, 2921, Rotation::BestFit));
        $packer->addItem(new TestItem('179', 267, 305, 76, 2921, Rotation::BestFit));
        $packer->addItem(new TestItem('180', 267, 305, 76, 2921, Rotation::BestFit));
        $packer->addItem(new TestItem('181', 267, 305, 76, 2921, Rotation::BestFit));
        $packer->addItem(new TestItem('182', 267, 305, 76, 2921, Rotation::BestFit));
        $packer->addItem(new TestItem('183', 267, 305, 76, 2921, Rotation::BestFit));
        $packer->addItem(new TestItem('184', 178, 540, 89, 1960, Rotation::BestFit));
        $packer->addItem(new TestItem('185', 178, 540, 89, 1960, Rotation::BestFit));
        $packer->addItem(new TestItem('186', 178, 540, 89, 1960, Rotation::BestFit));
        $packer->addItem(new TestItem('187', 178, 540, 89, 1960, Rotation::BestFit));
        $packer->addItem(new TestItem('188', 178, 279, 76, 299, Rotation::BestFit));
        $packer->addItem(new TestItem('189', 178, 279, 76, 299, Rotation::BestFit));
        $packer->addItem(new TestItem('190', 178, 279, 76, 299, Rotation::BestFit));
        $packer->addItem(new TestItem('191', 178, 279, 76, 299, Rotation::BestFit));
        $packer->addItem(new TestItem('192', 178, 279, 76, 299, Rotation::BestFit));
        $packer->addItem(new TestItem('193', 178, 279, 76, 299, Rotation::BestFit));
        $packer->addItem(new TestItem('194', 178, 279, 76, 299, Rotation::BestFit));
        $packer->addItem(new TestItem('195', 203, 203, 25, 417, Rotation::BestFit));
        $packer->addItem(new TestItem('196', 203, 203, 25, 417, Rotation::BestFit));
        $packer->addItem(new TestItem('197', 203, 203, 25, 417, Rotation::BestFit));
        $packer->addItem(new TestItem('198', 203, 203, 25, 417, Rotation::BestFit));
        $packer->addItem(new TestItem('199', 203, 203, 25, 417, Rotation::BestFit));
        $packer->addItem(new TestItem('200', 203, 203, 25, 417, Rotation::BestFit));
        $packer->addItem(new TestItem('201', 203, 203, 25, 417, Rotation::BestFit));
        $packer->addItem(new TestItem('202', 203, 203, 25, 417, Rotation::BestFit));
        $packer->addItem(new TestItem('203', 203, 203, 25, 417, Rotation::BestFit));
        $packer->addItem(new TestItem('204', 203, 203, 25, 417, Rotation::BestFit));
        $packer->addItem(new TestItem('205', 108, 159, 76, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('206', 108, 159, 76, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('207', 108, 159, 76, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('208', 108, 159, 76, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('209', 108, 159, 76, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('210', 108, 159, 76, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('211', 108, 159, 76, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('212', 127, 279, 70, 1188, Rotation::BestFit));
        $packer->addItem(new TestItem('213', 508, 1143, 127, 18144, Rotation::BestFit));
        $packer->addItem(new TestItem('214', 508, 1143, 127, 18144, Rotation::BestFit));
        $packer->addItem(new TestItem('215', 508, 1143, 127, 18144, Rotation::BestFit));
        $packer->addItem(new TestItem('216', 508, 1143, 127, 18144, Rotation::BestFit));
        $packer->addItem(new TestItem('217', 112, 831, 109, 2177, Rotation::BestFit));
        $packer->addItem(new TestItem('218', 112, 831, 109, 2177, Rotation::BestFit));
        $packer->addItem(new TestItem('219', 112, 831, 109, 2177, Rotation::BestFit));
        $packer->addItem(new TestItem('220', 112, 831, 109, 2177, Rotation::BestFit));
        $packer->addItem(new TestItem('221', 112, 831, 109, 2177, Rotation::BestFit));
        $packer->addItem(new TestItem('222', 112, 831, 109, 2177, Rotation::BestFit));
        $packer->addItem(new TestItem('223', 112, 831, 109, 2177, Rotation::BestFit));
        $packer->addItem(new TestItem('224', 112, 831, 109, 2177, Rotation::BestFit));
        $packer->addItem(new TestItem('225', 112, 831, 109, 2177, Rotation::BestFit));
        $packer->addItem(new TestItem('226', 112, 831, 109, 2177, Rotation::BestFit));
        $packer->addItem(new TestItem('227', 112, 831, 109, 2177, Rotation::BestFit));
        $packer->addItem(new TestItem('228', 112, 831, 109, 2177, Rotation::BestFit));
        $packer->addItem(new TestItem('229', 305, 737, 394, 16012, Rotation::BestFit));
        $packer->addItem(new TestItem('230', 305, 737, 394, 16012, Rotation::BestFit));
        $packer->addItem(new TestItem('231', 188, 231, 84, 544, Rotation::BestFit));
        $packer->addItem(new TestItem('232', 188, 231, 84, 544, Rotation::BestFit));
        $packer->addItem(new TestItem('233', 188, 231, 84, 544, Rotation::BestFit));
        $packer->addItem(new TestItem('234', 188, 231, 84, 544, Rotation::BestFit));
        $packer->addItem(new TestItem('235', 188, 231, 84, 544, Rotation::BestFit));
        $packer->addItem(new TestItem('236', 188, 231, 84, 544, Rotation::BestFit));
        $packer->addItem(new TestItem('237', 188, 231, 84, 544, Rotation::BestFit));
        $packer->addItem(new TestItem('238', 188, 231, 84, 544, Rotation::BestFit));
        $packer->addItem(new TestItem('239', 188, 231, 84, 544, Rotation::BestFit));
        $packer->addItem(new TestItem('240', 188, 231, 84, 544, Rotation::BestFit));
        $packer->addItem(new TestItem('241', 531, 1049, 112, 22680, Rotation::BestFit));
        $packer->addItem(new TestItem('242', 531, 1049, 112, 22680, Rotation::BestFit));
        $packer->addItem(new TestItem('243', 531, 1049, 112, 22680, Rotation::BestFit));
        $packer->addItem(new TestItem('244', 531, 1049, 112, 22680, Rotation::BestFit));
        $packer->addItem(new TestItem('245', 211, 206, 221, 1270, Rotation::BestFit));
        $packer->addItem(new TestItem('246', 211, 206, 221, 1270, Rotation::BestFit));
        $packer->addItem(new TestItem('247', 211, 206, 221, 1270, Rotation::BestFit));
        $packer->addItem(new TestItem('248', 211, 206, 221, 1270, Rotation::BestFit));
        $packer->addItem(new TestItem('249', 241, 559, 89, 3257, Rotation::BestFit));
        $packer->addItem(new TestItem('250', 241, 559, 89, 3257, Rotation::BestFit));
        $packer->addItem(new TestItem('251', 241, 559, 89, 3257, Rotation::BestFit));
        $packer->addItem(new TestItem('252', 241, 559, 89, 3257, Rotation::BestFit));
        $packer->addItem(new TestItem('253', 241, 559, 89, 3257, Rotation::BestFit));
        $packer->addItem(new TestItem('254', 241, 559, 89, 3257, Rotation::BestFit));
        $packer->addItem(new TestItem('255', 241, 559, 89, 3257, Rotation::BestFit));
        $packer->addItem(new TestItem('256', 241, 559, 89, 3257, Rotation::BestFit));
        $packer->addItem(new TestItem('257', 241, 559, 89, 3257, Rotation::BestFit));
        $packer->addItem(new TestItem('258', 241, 559, 89, 3257, Rotation::BestFit));
        $packer->addItem(new TestItem('259', 191, 279, 61, 1361, Rotation::BestFit));
        $packer->addItem(new TestItem('260', 191, 279, 61, 1361, Rotation::BestFit));
        $packer->addItem(new TestItem('261', 191, 279, 61, 1361, Rotation::BestFit));
        $packer->addItem(new TestItem('262', 191, 279, 61, 1361, Rotation::BestFit));
        $packer->addItem(new TestItem('263', 191, 279, 61, 1361, Rotation::BestFit));
        $packer->addItem(new TestItem('264', 191, 279, 61, 1361, Rotation::BestFit));
        $packer->addItem(new TestItem('265', 191, 279, 61, 1361, Rotation::BestFit));
        $packer->addItem(new TestItem('266', 191, 279, 61, 1361, Rotation::BestFit));
        $packer->addItem(new TestItem('267', 191, 279, 61, 1361, Rotation::BestFit));
        $packer->addItem(new TestItem('268', 191, 279, 61, 1361, Rotation::BestFit));
        $packer->addItem(new TestItem('269', 660, 1022, 330, 29102, Rotation::BestFit));
        $packer->addItem(new TestItem('270', 413, 413, 681, 15876, Rotation::BestFit));
        $packer->addItem(new TestItem('271', 413, 413, 681, 15876, Rotation::BestFit));
        $packer->addItem(new TestItem('272', 413, 413, 681, 15876, Rotation::BestFit));
        $packer->addItem(new TestItem('273', 413, 413, 681, 15876, Rotation::BestFit));
        $packer->addItem(new TestItem('274', 508, 508, 737, 13608, Rotation::BestFit));
        $packer->addItem(new TestItem('275', 508, 508, 737, 13608, Rotation::BestFit));
        $packer->addItem(new TestItem('276', 578, 635, 787, 40007, Rotation::BestFit));
        $packer->addItem(new TestItem('277', 578, 635, 787, 40007, Rotation::BestFit));
        $packer->addItem(new TestItem('278', 203, 203, 25, 753, Rotation::BestFit));
        $packer->addItem(new TestItem('279', 203, 203, 25, 753, Rotation::BestFit));
        $packer->addItem(new TestItem('280', 203, 203, 25, 753, Rotation::BestFit));
        $packer->addItem(new TestItem('281', 203, 203, 25, 753, Rotation::BestFit));
        $packer->addItem(new TestItem('282', 203, 203, 25, 481, Rotation::BestFit));
        $packer->addItem(new TestItem('283', 203, 203, 25, 481, Rotation::BestFit));
        $packer->addItem(new TestItem('284', 203, 203, 25, 481, Rotation::BestFit));
        $packer->addItem(new TestItem('285', 203, 203, 25, 481, Rotation::BestFit));
        $packer->addItem(new TestItem('286', 203, 203, 25, 481, Rotation::BestFit));
        $packer->addItem(new TestItem('287', 203, 203, 25, 481, Rotation::BestFit));
        $packer->addItem(new TestItem('288', 124, 254, 86, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('289', 124, 254, 86, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('290', 124, 254, 86, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('291', 124, 254, 86, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('292', 124, 254, 86, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('293', 124, 254, 86, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('294', 124, 254, 86, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('295', 124, 254, 86, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('296', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('297', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('298', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('299', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('300', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('301', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('302', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('303', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('304', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('305', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('306', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('307', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('308', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('309', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('310', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('311', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('312', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('313', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('314', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('315', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('316', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('317', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('318', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('319', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('320', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('321', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('322', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('323', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('324', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('325', 146, 229, 13, 227, Rotation::BestFit));
        $packer->addItem(new TestItem('326', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('327', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('328', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('329', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('330', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('331', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('332', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('333', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('334', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('335', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('336', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('337', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('338', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('339', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('340', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('341', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('342', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('343', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('344', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('345', 140, 267, 38, 372, Rotation::BestFit));
        $packer->addItem(new TestItem('346', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('347', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('348', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('349', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('350', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('351', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('352', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('353', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('354', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('355', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('356', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('357', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('358', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('359', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('360', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('361', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('362', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('363', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('364', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('365', 89, 909, 89, 3447, Rotation::BestFit));
        $packer->addItem(new TestItem('366', 107, 163, 81, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('367', 107, 163, 81, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('368', 107, 163, 81, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('369', 107, 163, 81, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('370', 107, 163, 81, 635, Rotation::BestFit));
        $packer->addItem(new TestItem('371', 241, 559, 89, 3284, Rotation::BestFit));
        $packer->addItem(new TestItem('372', 318, 445, 114, 3475, Rotation::BestFit));
        $packer->addItem(new TestItem('373', 330, 292, 292, 6350, Rotation::BestFit));
        $packer->addItem(new TestItem('374', 330, 292, 292, 6350, Rotation::BestFit));
        $packer->addItem(new TestItem('375', 330, 292, 292, 6350, Rotation::BestFit));
        $packer->addItem(new TestItem('376', 330, 292, 292, 6350, Rotation::BestFit));
        $packer->addItem(new TestItem('377', 324, 445, 318, 3819, Rotation::BestFit));
        $packer->addItem(new TestItem('378', 324, 445, 318, 3819, Rotation::BestFit));
        $packer->addItem(new TestItem('379', 324, 445, 318, 3819, Rotation::BestFit));
        $packer->addItem(new TestItem('380', 324, 445, 318, 3819, Rotation::BestFit));
        $packer->addItem(new TestItem('381', 324, 445, 318, 3819, Rotation::BestFit));
        $packer->addItem(new TestItem('382', 324, 445, 318, 3819, Rotation::BestFit));
        $packer->addItem(new TestItem('383', 229, 572, 127, 3411, Rotation::BestFit));
        $packer->addItem(new TestItem('384', 279, 330, 51, 1157, Rotation::BestFit));
        $packer->addItem(new TestItem('385', 203, 203, 25, 159, Rotation::BestFit));
        $packer->addItem(new TestItem('386', 203, 203, 25, 159, Rotation::BestFit));
        $packer->addItem(new TestItem('387', 203, 203, 25, 159, Rotation::BestFit));
        $packer->addItem(new TestItem('388', 203, 203, 25, 159, Rotation::BestFit));
        $packer->addItem(new TestItem('389', 203, 203, 25, 159, Rotation::BestFit));
        $packer->addItem(new TestItem('390', 203, 203, 25, 159, Rotation::BestFit));
        $packer->addItem(new TestItem('391', 203, 203, 25, 159, Rotation::BestFit));
        $packer->addItem(new TestItem('392', 203, 203, 25, 159, Rotation::BestFit));
        $packer->addItem(new TestItem('393', 203, 203, 51, 354, Rotation::BestFit));
        $packer->addItem(new TestItem('394', 178, 178, 25, 91, Rotation::BestFit));
        $packer->addItem(new TestItem('395', 178, 178, 25, 91, Rotation::BestFit));
        $packer->addItem(new TestItem('396', 178, 178, 25, 91, Rotation::BestFit));
        $packer->addItem(new TestItem('397', 95, 127, 25, 73, Rotation::BestFit));
        $packer->addItem(new TestItem('398', 95, 127, 25, 73, Rotation::BestFit));
        $packer->addItem(new TestItem('399', 95, 127, 25, 73, Rotation::BestFit));
        $packer->addItem(new TestItem('400', 95, 127, 25, 73, Rotation::BestFit));
        $packer->addItem(new TestItem('401', 95, 127, 25, 73, Rotation::BestFit));
        $packer->addItem(new TestItem('402', 95, 127, 25, 73, Rotation::BestFit));
        $packer->addItem(new TestItem('403', 95, 127, 25, 73, Rotation::BestFit));
        $packer->addItem(new TestItem('404', 95, 127, 25, 73, Rotation::BestFit));
        $packer->addItem(new TestItem('405', 95, 127, 25, 73, Rotation::BestFit));
        $packer->addItem(new TestItem('406', 95, 127, 25, 73, Rotation::BestFit));
        $packer->addItem(new TestItem('407', 64, 165, 122, 1134, Rotation::BestFit));
        $packer->addItem(new TestItem('408', 64, 165, 122, 1134, Rotation::BestFit));
        $packer->addItem(new TestItem('409', 64, 165, 122, 1134, Rotation::BestFit));
        $packer->addItem(new TestItem('410', 64, 165, 122, 1134, Rotation::BestFit));
        $packer->addItem(new TestItem('411', 86, 109, 51, 200, Rotation::BestFit));
        $packer->addItem(new TestItem('412', 86, 109, 51, 200, Rotation::BestFit));
        $packer->addItem(new TestItem('413', 86, 109, 51, 200, Rotation::BestFit));
        $packer->addItem(new TestItem('414', 86, 109, 51, 200, Rotation::BestFit));
        $packer->addItem(new TestItem('415', 86, 109, 51, 200, Rotation::BestFit));
        $packer->addItem(new TestItem('416', 86, 109, 51, 200, Rotation::BestFit));
        $packer->addItem(new TestItem('417', 305, 521, 108, 2976, Rotation::BestFit));
        $packer->addItem(new TestItem('418', 305, 521, 108, 2976, Rotation::BestFit));

        $packedBoxes = $packer->pack();

        self::assertCount(42, $packedBoxes);
        self::assertCount(62, $packer->getUnpackedItems());
    }

    public function testCustomPackedBoxSorterIsUsed(): void
    {
        PackedBoxByReferenceSorter::$reference = 'Box #1';
        $packer = new Packer();
        $packer->setPackedBoxSorter(new PackedBoxByReferenceSorter());
        $packer->addBox(new TestBox('Box #1', 1, 1, 1, 0, 1, 1, 1, PHP_INT_MAX));
        $packer->addBox(new TestBox('Box #2', 1, 1, 1, 0, 1, 1, 1, PHP_INT_MAX));
        $packer->addItem(new TestItem('Item', 1, 1, 1, 1, Rotation::BestFit), 2);
        $packedBoxes = iterator_to_array($packer->pack());

        self::assertCount(2, $packedBoxes);
        self::assertEquals('Box #1', $packedBoxes[0]->getBox()->getReference());

        PackedBoxByReferenceSorter::$reference = 'Box #2';
        $packer = new Packer();
        $packer->setPackedBoxSorter(new PackedBoxByReferenceSorter());
        $packer->addBox(new TestBox('Box #1', 1, 1, 1, 0, 1, 1, 1, PHP_INT_MAX));
        $packer->addBox(new TestBox('Box #2', 1, 1, 1, 0, 1, 1, 1, PHP_INT_MAX));
        $packer->addItem(new TestItem('Item', 1, 1, 1, 1, Rotation::BestFit), 2);
        $packedBoxes = iterator_to_array($packer->pack());

        self::assertCount(2, $packedBoxes);
        self::assertEquals('Box #2', $packedBoxes[0]->getBox()->getReference());
    }

    public function testNotStrictItemOrdering(): void
    {
        $packer = new Packer();
        $packer->setMaxBoxesToBalanceWeight(0);
        $packer->addBox(new TestBox('Box', 3, 3, 3, 0, 3, 3, 3, PHP_INT_MAX));
        $packer->addItem(new TestItem('Item #1', 1, 1, 1, 1, Rotation::BestFit), 18);
        $packer->addItem(new TestItem('Item #2', 2, 2, 2, 2, Rotation::BestFit), 2);
        $packedBoxes = iterator_to_array($packer->pack());

        self::assertCount(2, $packedBoxes);

        $box1Items = $packedBoxes[0]->getItems()->asItemArray();
        self::assertEquals('Item #2', $box1Items[0]->getDescription());
        self::assertEquals('Item #1', $box1Items[1]->getDescription());
        self::assertEquals('Item #1', $box1Items[2]->getDescription());
        self::assertEquals('Item #1', $box1Items[3]->getDescription());
        self::assertEquals('Item #1', $box1Items[4]->getDescription());
        self::assertEquals('Item #1', $box1Items[5]->getDescription());
        self::assertEquals('Item #1', $box1Items[6]->getDescription());
        self::assertEquals('Item #1', $box1Items[7]->getDescription());
        self::assertEquals('Item #1', $box1Items[8]->getDescription());
        self::assertEquals('Item #1', $box1Items[9]->getDescription());
        self::assertEquals('Item #1', $box1Items[10]->getDescription());
        self::assertEquals('Item #1', $box1Items[11]->getDescription());
        self::assertEquals('Item #1', $box1Items[12]->getDescription());
        self::assertEquals('Item #1', $box1Items[13]->getDescription());
        self::assertEquals('Item #1', $box1Items[14]->getDescription());
        self::assertEquals('Item #1', $box1Items[15]->getDescription());
        self::assertEquals('Item #1', $box1Items[16]->getDescription());
        self::assertEquals('Item #1', $box1Items[17]->getDescription());

        $box2Items = $packedBoxes[1]->getItems()->asItemArray();
        self::assertEquals('Item #2', $box2Items[0]->getDescription());
        self::assertEquals('Item #1', $box2Items[1]->getDescription());
    }

    public function testStrictItemOrdering(): void
    {
        $packer = new Packer();
        $packer->beStrictAboutItemOrdering(true);
        $packer->addBox(new TestBox('Box', 3, 3, 3, 0, 3, 3, 3, PHP_INT_MAX));
        $packer->addItem(new TestItem('Item #1', 1, 1, 1, 1, Rotation::BestFit), 18);
        $packer->addItem(new TestItem('Item #2', 2, 2, 2, 2, Rotation::BestFit), 2);
        $packedBoxes = iterator_to_array($packer->pack());

        self::assertCount(3, $packedBoxes);

        $box1Items = $packedBoxes[0]->getItems()->asItemArray();
        self::assertEquals('Item #2', $box1Items[0]->getDescription());
        self::assertEquals('Item #1', $box1Items[1]->getDescription());
        self::assertEquals('Item #1', $box1Items[2]->getDescription());
        self::assertEquals('Item #1', $box1Items[3]->getDescription());
        self::assertEquals('Item #1', $box1Items[4]->getDescription());
        self::assertEquals('Item #1', $box1Items[5]->getDescription());
        self::assertEquals('Item #1', $box1Items[6]->getDescription());
        self::assertEquals('Item #1', $box1Items[7]->getDescription());
        self::assertEquals('Item #1', $box1Items[8]->getDescription());
        self::assertEquals('Item #1', $box1Items[9]->getDescription());
        self::assertEquals('Item #1', $box1Items[10]->getDescription());
        self::assertEquals('Item #1', $box1Items[11]->getDescription());
        self::assertEquals('Item #1', $box1Items[12]->getDescription());
        self::assertEquals('Item #1', $box1Items[13]->getDescription());
        self::assertEquals('Item #1', $box1Items[14]->getDescription());
        self::assertEquals('Item #1', $box1Items[15]->getDescription());
        self::assertEquals('Item #1', $box1Items[16]->getDescription());
        self::assertEquals('Item #1', $box1Items[17]->getDescription());

        $box2Items = $packedBoxes[1]->getItems()->asItemArray();
        self::assertEquals('Item #2', $box2Items[0]->getDescription());

        $box3Items = $packedBoxes[2]->getItems()->asItemArray();
        self::assertEquals('Item #1', $box3Items[0]->getDescription());
    }

    public function testAllPermutationsSimpleCase(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box A', 36, 8, 3, 0, 36, 8, 3, 2));
        $packer->addBox(new TestBox('Box B', 36, 8, 8, 0, 36, 8, 8, 2));
        $packer->addItem(new TestItem('Item 1', 35, 7, 2, 1, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 2', 6, 5, 1, 1, Rotation::BestFit));

        $permutations = $packer->packAllPermutations();
        self::assertCount(2, $permutations);

        $firstPermutation = $permutations[0];
        self::assertCount(1, $firstPermutation); // 1 box
        self::assertCount(2, $firstPermutation->top()->getItems());

        $secondPermutation = $permutations[1];
        self::assertCount(1, $secondPermutation); // 1 box
        self::assertCount(2, $secondPermutation->top()->getItems());
    }

    /**
     * Test that unlimited supply boxes are handled correctly.
     */
    public function testAllPermutationsUnlimitedSupplyBox(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Light box', 100, 100, 100, 1, 100, 100, 100, 100));
        $packer->addBox(new TestBox('Heavy box', 100, 100, 100, 100, 100, 100, 100, 10000));

        $packer->addItem(new TestItem('Item', 100, 100, 100, 75, Rotation::BestFit), 3);

        $permutations = $packer->packAllPermutations();
        self::assertCount(8, $permutations);
    }

    /**
     * Test that limited supply boxes are handled correctly.
     */
    public function testAllPermutationsLimitedSupplyBox(): void
    {
        // as above, but limit light box to quantity 2
        $packer = new Packer();
        $packer->addBox(new LimitedSupplyTestBox('Light box', 100, 100, 100, 1, 100, 100, 100, 100, 2));
        $packer->addBox(new TestBox('Heavy box', 100, 100, 100, 100, 100, 100, 100, 10000));

        $packer->addItem(new TestItem('Item', 100, 100, 100, 75, Rotation::BestFit), 3);

        $permutations = $packer->packAllPermutations();
        self::assertCount(7, $permutations);
    }

    /**
     * Test that limited supply boxes are handled correctly.
     */
    public function testAllPermutationsNotEnoughLimitedSupplyBox(): void
    {
        // as above, but remove heavy box as an option
        $this->expectException(NoBoxesAvailableException::class);
        $packer = new Packer();
        $packer->addBox(new LimitedSupplyTestBox('Light box', 100, 100, 100, 1, 100, 100, 100, 100, 2));
        $packer->addItem(new TestItem('Item', 100, 100, 100, 75, Rotation::BestFit), 3);

        $permutations = $packer->packAllPermutations();
    }

    public function testAllPermutationsPackThreeItemsOneDoesntFitInAnyBoxWhenNotThrowing(): void
    {
        $box1 = new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000);
        $box2 = new TestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000);

        $item1 = new TestItem('Item 1', 2500, 2500, 20, 2000, Rotation::BestFit);
        $item2 = new TestItem('Item 2', 25000, 2500, 20, 2000, Rotation::BestFit);
        $item3 = new TestItem('Item 3', 2500, 2500, 20, 2000, Rotation::BestFit);

        $packer = new Packer();
        $packer->throwOnUnpackableItem(false);
        $packer->addBox($box1);
        $packer->addBox($box2);
        $packer->addItem($item1);
        $packer->addItem($item2);
        $packer->addItem($item3);

        $permutations = $packer->packAllPermutations();
        self::assertCount(1, $permutations);
    }

    public function testAllPermutationsTooLargeItemsHandledWhenNotThrowing(): void
    {
        $packer = new Packer();
        $packer->throwOnUnpackableItem(false);
        $packer->addBox(new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000));
        $packer->addBox(new TestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000));
        $packer->addItem(new TestItem('Item 1', 2500, 2500, 20, 2000, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 2', 25000, 2500, 20, 2000, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 3', 2500, 2500, 20, 2000, Rotation::BestFit));

        $permutations = $packer->packAllPermutations();
        self::assertCount(1, $permutations);
        self::assertCount(1, $packer->getUnpackedItems());
    }

    public function testAllPermutationsUnpackableItemsHandledWhenNotThrowing(): void
    {
        $packer = new Packer();
        $packer->throwOnUnpackableItem(false);
        $packer->addBox(new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000));
        $packer->addBox(new LimitedSupplyTestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000, 0));
        $packer->addItem(new TestItem('Item 1', 2500, 2500, 20, 2000, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 2', 25000, 2500, 20, 2000, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 3', 2500, 2500, 20, 2000, Rotation::BestFit));

        $permutations = $packer->packAllPermutations();
        self::assertCount(0, $permutations);
        self::assertCount(3, $packer->getUnpackedItems());
    }
}
