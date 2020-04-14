<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\LimitedSupplyTestBox;
use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;
use function iterator_to_array;
use PHPUnit\Framework\TestCase;

class PackerTest extends TestCase
{
    public function testPackThreeItemsOneDoesntFitInAnyBox(): void
    {
        $this->expectException(ItemTooLargeException::class);
        $box1 = new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000);
        $box2 = new TestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000);

        $item1 = new TestItem('Item 1', 2500, 2500, 20, 2000, true);
        $item2 = new TestItem('Item 2', 25000, 2500, 20, 2000, true);
        $item3 = new TestItem('Item 3', 2500, 2500, 20, 2000, true);

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
        $this->expectException(ItemTooLargeException::class);
        $item1 = new TestItem('Item 1', 2500, 2500, 20, 2000, true);
        $item2 = new TestItem('Item 2', 25000, 2500, 20, 2000, true);
        $item3 = new TestItem('Item 3', 2500, 2500, 20, 2000, true);

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
        $packer->addItem(new TestItem('Item', 1, 1, 1, 1, false), 4);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(2, $packedBoxes[0]->getItems());
        self::assertCount(2, $packedBoxes[1]->getItems());

        // same items, but with redistribution turned off - expecting 3+1 based on pure fit
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 1, 1, 3, 0, 1, 1, 3, 3));
        $packer->addItem(new TestItem('Item', 1, 1, 1, 1, false), 4);
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
        $packer->addItem(new TestItem('Item', 15, 13, 8, 407, true), 2);
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
        $packer->addItem(new TestItem('Item 1', 220, 310, 12, 679, true));
        $packer->addItem(new TestItem('Item 2', 210, 297, 11, 648, true));
        $packer->addItem(new TestItem('Item 3', 210, 297, 5, 187, true));
        $packer->addItem(new TestItem('Item 4', 148, 210, 32, 880, true));
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
        $packer->addItem(new TestItem('Item 1', 210, 297, 4, 213, true));
        $packer->addItem(new TestItem('Item 2', 80, 285, 70, 199, true));
        $packer->addItem(new TestItem('Item 3', 80, 285, 70, 199, true));

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
        $packer->addItem(new TestItem('Item 1', 35, 7, 2, 1, false));
        $packer->addItem(new TestItem('Item 2', 6, 5, 1, 1, false));
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
        $packer->addItem(new TestItem('Item 1', 1, 1, 1, 100, false));
        $packer->addItem(new TestItem('Item 2', 1, 1, 1, 100, false));
        $packer->addItem(new TestItem('Item 3', 1, 1, 1, 100, false));
        $packer->addItem(new TestItem('Item 4', 1, 1, 1, 100, false));
        $packer->addItem(new TestItem('Item 5', 2, 2, 2, 100, false));
        $packer->addItem(new TestItem('Item 6', 2, 2, 2, 100, false));
        $packer->addItem(new TestItem('Item 7', 2, 2, 2, 100, false));
        $packer->addItem(new TestItem('Item 8', 2, 2, 2, 100, false));
        $packer->addItem(new TestItem('Item 9', 4, 4, 4, 100, false));

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
        $packer->addItem(new TestItem('Item', 55, 85, 122, 350, false));

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(1, $packedBoxes);
        self::assertEquals('Small', $packedBoxes[0]->getBox()->getReference());
    }

    /**
     * From issue #170.
     */
    public function testIssue170(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 170, 120, 120, 2000, 170, 120, 120, 60000));
        $packer->addItem(new TestItem('Item', 70, 130, 2, 657, false), 100);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(2, $packedBoxes);
    }

    /**
     * From issue #182.
     */
    public function testIssue182A(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 410, 310, 310, 2000, 410, 310, 310, 60000));
        $packer->addBox(new TestBox('Box', 410, 310, 260, 2000, 410, 310, 260, 60000));
        $packer->addBox(new TestBox('Box', 410, 310, 205, 2000, 410, 310, 205, 60000));
        $packer->addBox(new TestBox('Box', 310, 310, 210, 2000, 310, 310, 210, 60000));
        $packer->addBox(new TestBox('Box', 310, 210, 210, 2000, 310, 210, 210, 60000));
        $packer->addBox(new TestBox('Box', 310, 210, 155, 2000, 310, 210, 155, 60000));
        $packer->addBox(new TestBox('Box', 210, 160, 105, 2000, 210, 160, 105, 60000));

        $packer->addItem(new TestItem('Item', 150, 100, 100, 1, false), 200);

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

        $packer->addItem(new TestItem('Item', 100, 100, 100, 75, false), 3);

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

        $packer->addItem(new TestItem('Item', 100, 100, 100, 75, false), 3);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(3, $packedBoxes);
        self::assertEquals('Light box', $packedBoxes[0]->getBox()->getReference());
        self::assertEquals('Light box', $packedBoxes[1]->getBox()->getReference());
        self::assertEquals('Heavy box', $packedBoxes[2]->getBox()->getReference());
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
        $packer->addItem(new TestItem('Item', 100, 100, 100, 75, false), 3);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(3, $packedBoxes);
        self::assertEquals('Light box', $packedBoxes[0]->getBox()->getReference());
        self::assertEquals('Light box', $packedBoxes[1]->getBox()->getReference());
        self::assertEquals('Heavy box', $packedBoxes[2]->getBox()->getReference());
    }

    /**
     * From issue #191.
     */
    public function testIssue191(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('B 1', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 400, 150, 200, 10, false), 2);
        self::assertCount(1, $packer->pack());

        $packer = new Packer();
        $packer->addBox(new TestBox('B 1', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 400, 200, 150, 10, false), 2);
        self::assertCount(1, $packer->pack());

        $packer = new Packer();
        $packer->addBox(new TestBox('B 1', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 200, 400, 150, 10, false), 2);
        self::assertCount(1, $packer->pack());

        $packer = new Packer();
        $packer->addBox(new TestBox('B 1', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 400, 150, 200, 10, false), 1);
        $packer->addItem(new TestItem('Item 2', 400, 200, 150, 10, false), 1);
        self::assertCount(1, $packer->pack());

        $packer = new Packer();
        $packer->addBox(new TestBox('B 1', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 400, 200, 150, 10, false), 1);
        $packer->addItem(new TestItem('Item 2', 400, 150, 200, 10, false), 1);
        self::assertCount(1, $packer->pack());
    }

    /**
     * From issue #192.
     */
    public function testIssue192(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 350, 250, 55, 10, false));
        $packer->addItem(new TestItem('Item 2', 225, 180, 55, 10, false));
        $packer->addItem(new TestItem('Item 3', 265, 195, 30, 10, false));
        $packer->addItem(new TestItem('Item 4', 260, 190, 30, 10, false));
        self::assertCount(1, $packer->pack());

        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 345, 250, 55, 10, false));
        $packer->addItem(new TestItem('Item 2', 225, 180, 55, 10, false));
        $packer->addItem(new TestItem('Item 3', 265, 195, 30, 10, false));
        $packer->addItem(new TestItem('Item 4', 260, 190, 30, 10, false));
        self::assertCount(1, $packer->pack());

        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 350, 250, 55, 10, false));
        $packer->addItem(new TestItem('Item 2', 225, 180, 50, 10, false));
        $packer->addItem(new TestItem('Item 3', 265, 195, 30, 10, false));
        $packer->addItem(new TestItem('Item 4', 260, 190, 30, 10, false));
        self::assertCount(1, $packer->pack());

        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 360, 250, 55, 10, false));
        $packer->addItem(new TestItem('Item 2', 225, 180, 55, 10, false));
        $packer->addItem(new TestItem('Item 3', 265, 195, 30, 10, false));
        $packer->addItem(new TestItem('Item 4', 260, 190, 30, 10, false));
        self::assertCount(1, $packer->pack());

        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 400, 300, 200, 10, 400, 300, 200, 1000));
        $packer->addItem(new TestItem('Item 1', 350, 250, 55, 10, false));
        $packer->addItem(new TestItem('Item 2', 225, 180, 60, 10, false));
        $packer->addItem(new TestItem('Item 3', 265, 195, 30, 10, false));
        $packer->addItem(new TestItem('Item 4', 260, 190, 30, 10, false));
        self::assertCount(1, $packer->pack());
    }

    /**
     * From issue #196.
     */
    public function testIssue196(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('[Box]', 360, 620, 370, 1, 360, 620, 370, 29000));
        $packer->addItem(new TestItem('C5 240 x 165 mm, 1000 vnt.', 259, 375, 99, 5440, false), 4);
        $packer->addItem(new TestItem('170x230+30mm', 200, 300, 10, 194, false), 12);
        $packer->addItem(new TestItem('50 mm x 66 m, ruda', 100, 100, 50, 165, false), 12);
        self::assertCount(1, $packer->pack());

        $packer = new Packer();
        $packer->addBox(new TestBox('[Box]', 360, 620, 370, 1, 360, 620, 370, 29000));
        $packer->addItem(new TestItem('C5 240 x 165 mm, 1000 vnt.', 259, 375, 99, 5440, false), 4);
        $packer->addItem(new TestItem('170x230+30mm', 200, 300, 10, 194, false), 12);
        $packer->addItem(new TestItem('50 mm x 66 m, ruda', 100, 100, 50, 165, false), 12);
        $packer->addItem(new TestItem('175 x 255, B5', 130, 210, 30, 391, false), 2);
        self::assertCount(1, $packer->pack());
    }

    /**
     * From PR #198, tests with an atypically large number of boxes.
     */
    public function testNumberOfBoxesTorture(): void
    {
        $packer = new Packer();
        $boxes = [
            ['W10 - Plain Box 24x14x9.5', 14, 24, 9.5, 1, 14, 24, 9.5, 60],
            ['Box- 36x24x10', 24, 36, 10, 3.7, 24, 36, 10, 60],
            ['Box- 12x12x12', 12, 12, 12, 1, 12, 12, 12, 60],
            ['Z4 - 28x22x12', 22, 28, 12, 2, 22, 28, 12, 60],
            ['Z3 - 33x14x12', 14, 33, 12, 2, 14, 33, 12, 60],
            ['Z2 - 22x14x12', 14, 22, 12, 2, 14, 22, 12, 60],
            ['PB2 - Plain Box 80x3x3 (FOL)', 3, 80, 3, 2, 3, 80, 3, 60],
            ['PB3 - Plain Box 6x4x80 (FOL)', 4, 6, 80, 2, 4, 6, 80, 60],
            ['Box 1678 - 44 x 17 x 8', 17, 44, 8, 3.6, 17, 44, 8, 60],
            ['Box 1677 - 44 x 17 x 6', 17, 44, 6, 3.4, 17, 44, 6, 60],
            ['Box 1671 - 30 x 4 x 4', 4, 30, 4, 0.4, 4, 30, 4, 60],
            ['Box 1645 - 84 x 9 x 8', 9, 84, 8, 2.3, 9, 84, 8, 60],
            ['Box 1644 - 84 x 9 x 6', 9, 84, 6, 2.2, 9, 84, 6, 60],
            ['Box 1642 - 84 x 6 x 6', 6, 84, 6, 1.8, 6, 84, 6, 60],
            ['Box 1634 - 80 x 4 x 4', 4, 80, 4, 1.2, 4, 80, 4, 60],
            ['Box 1630 - 72 x 9 x 4', 9, 72, 4, 1.6, 9, 72, 4, 60],
            ['Box 1626 - 72 x 4 x 4', 4, 72, 4, 1.1, 4, 72, 4, 60],
            ['Box 1608 - 60 x 16 x 12', 16, 60, 12, 4.6, 16, 60, 12, 60],
            ['Box 1571 - 48 x 24 x 12', 24, 48, 12, 3.6, 24, 48, 12, 60],
            ['Box 1566 - 48 x 16 x 12', 16, 48, 12, 3.9, 16, 48, 12, 60],
            ['Box 1565 - 48 x 12 x 12', 12, 48, 12, 2.5, 12, 48, 12, 60],
            ['Box 1564 - 48 x 12 x 4', 12, 48, 4, 1.4, 12, 48, 4, 60],
            ['Box 1562 - 48 x 9 x 4', 9, 48, 4, 1.1, 9, 48, 4, 60],
            ['Box 1553 - 44 x 24 x 12', 24, 44, 12, 3.4, 24, 44, 12, 60],
            ['Box 1529 - 42 x 15 x 4', 15, 42, 4, 1.3, 15, 42, 4, 60],
            ['Box 1527 - 42 x 12 x 4', 12, 42, 4, 1.1, 12, 42, 4, 60],
            ['Box 1525 - 42 x 9 x 4', 9, 42, 4, 0.9, 9, 42, 4, 60],
            ['Box 1523 - 42 x 6 x 4', 6, 42, 4, 0.7, 6, 42, 4, 60],
            ['Box 1521 - 42 x 4 x 4', 4, 42, 4, 0.6, 4, 42, 4, 60],
            ['Box 1475 - 39 x 8 x 6', 8, 39, 6, 0.9, 8, 39, 6, 60],
            ['Box 1469 - 36 x 36 x 11', 36, 36, 11, 3.1, 36, 36, 11, 60],
            ['Box 1467 - 36 x 36 x 4', 36, 36, 4, 2.5, 36, 36, 4, 60],
            ['Box 1466 - 36 x 36 x 3', 36, 36, 3, 2.3, 36, 36, 3, 60],
            ['Box 1457 - 36 x 30 x 9', 30, 36, 9, 2.5, 30, 36, 9, 60],
            ['Box 1444 - 36 x 24 x 9', 24, 36, 9, 2.1, 24, 36, 9, 60],
            ['Box 1443 - 36 x 24 x 6', 24, 36, 6, 2, 24, 36, 6, 60],
            ['Box 1442 - 36 x 24 x 4', 24, 36, 4, 1.7, 24, 36, 4, 60],
            ['Box 1441 - 36 x 24 x 3', 24, 36, 3, 1.6, 24, 36, 3, 60],
            ['Box 1432 - 36 x 20 x 14', 20, 36, 14, 2.7, 20, 36, 14, 60],
            ['Box 1431 - 36 x 20 x 12', 20, 36, 12, 2.5, 20, 36, 12, 60],
            ['Box 1421 - 36 x 16 x 16', 16, 36, 16, 2.3, 16, 36, 16, 60],
            ['Box 1419 - 36 x 16 x 12', 16, 36, 12, 3.2, 16, 36, 12, 60],
            ['Box 1412 - 36 x 14 x 12', 14, 36, 12, 2.8, 14, 36, 12, 60],
            ['Box 1392 - 36 x 6 x 6', 6, 36, 6, 0.9, 6, 36, 6, 60],
            ['Box 1391 - 36 x 6 x 4', 6, 36, 4, 0.7, 6, 36, 4, 60],
            ['Box 1328 - 33 x 20 x 14', 20, 33, 14, 2.2, 20, 33, 14, 60],
            ['Box 1291 - 32 x 12 x 8', 12, 32, 8, 1.4, 12, 32, 8, 60],
            ['Box 1281 - 32 x 6 x 6', 6, 32, 6, 0.8, 6, 32, 6, 60],
            ['Box 1213 - 30 x 28 x 18', 28, 30, 18, 3.7, 28, 30, 18, 60],
            ['Box 1171 - 30 x 20 x 18', 20, 30, 18, 2.7, 20, 30, 18, 60],
            ['Box 1159 - 30 x 18 x 10', 18, 30, 10, 1.9, 18, 30, 10, 60],
            ['Box 1131 - 30 x 6 x 4', 6, 30, 4, 0.5, 6, 30, 4, 60],
            ['Box 1126 - 28 x 28 x 12', 28, 28, 12, 2.2, 28, 28, 12, 60],
            ['Box 1125 - 28 x 28 x 10', 28, 28, 10, 2.6, 28, 28, 10, 60],
            ['Box 1108 - 28 x 24 x 14', 24, 28, 14, 2.2, 24, 28, 14, 60],
            ['Box 1091 - 28 x 20 x 18', 20, 28, 18, 2.5, 20, 28, 18, 60],
            ['Box 1081 - 28 x 18 x 14', 18, 28, 14, 2.1, 18, 28, 14, 60],
            ['Box 1031 - 26 x 22 x 10', 22, 26, 10, 2.2, 22, 26, 10, 60],
            ['Box 1025 - 26 x 20 x 15', 20, 26, 15, 2.3, 20, 26, 15, 60],
            ['Box 1023 - 26 x 20 x 12', 20, 26, 12, 2.1, 20, 26, 12, 60],
            ['Box 1019 - 26 x 20 x 4', 20, 26, 4, 1.6, 20, 26, 4, 60],
            ['Box 1000 - 26 x 14 x 8', 14, 26, 8, 1.3, 14, 26, 8, 60],
            ['Box 998 - 26 x 14 x 4', 14, 26, 4, 1, 14, 26, 4, 60],
            ['Box 979 - 24 x 24 x 16', 24, 24, 16, 2.2, 24, 24, 16, 60],
            ['Box 974 - 24 x 24 x 6', 24, 24, 6, 2, 24, 24, 6, 60],
            ['Box 938 - 24 x 14 x 12', 14, 24, 12, 1.4, 14, 24, 12, 60],
            ['Box 919 - 22 x 22 x 22', 22, 22, 22, 2.7, 22, 22, 22, 60],
            ['Box 911 - 22 x 22 x 6', 22, 22, 6, 1.7, 22, 22, 6, 60],
            ['Box 866 - 22 x 6 x 4', 6, 22, 4, 0.4, 6, 22, 4, 60],
            ['Box 864 - 20 x 20 x 8', 20, 20, 8, 1.6, 20, 20, 8, 60],
            ['Box 769 - 20 x 10 x 9', 10, 20, 9, 0.8, 10, 20, 9, 60],
            ['Box 640 - 19 x 8 x 7', 8, 19, 7, 0.6, 8, 19, 7, 60],
            ['Box 619 - 18 x 18 x 7', 18, 18, 7, 1.3, 18, 18, 7, 60],
            ['Box 597 - 18 x 16 x 12', 16, 18, 12, 1.4, 16, 18, 12, 60],
            ['Box 553 - 18 x 12 x 10', 12, 18, 10, 0.9, 12, 18, 10, 60],
            ['Box 533 - 18 x 10 x 5', 10, 18, 5, 0.6, 10, 18, 5, 60],
            ['Box 468 - 17 x 14 x 7', 14, 17, 7, 0.9, 14, 17, 7, 60],
            ['Box 407 - 16 x 16 x 10', 16, 16, 10, 1.2, 16, 16, 10, 60],
            ['Box 359 - 16 x 12 x 4', 12, 16, 4, 0.7, 12, 16, 4, 60],
            ['Box 336 - 16 x 8 x 7', 8, 16, 7, 0.5, 8, 16, 7, 60],
            ['Box 242 - 14 x 14 x 4', 14, 14, 4, 0.7, 14, 14, 4, 60],
            ['Box 168 - 13 x 11 x 8', 11, 13, 8, 0.7, 11, 13, 8, 60],
            ['Box 140 - 13 x 6 x 5', 6, 13, 5, 0.3, 6, 13, 5, 60],
            ['Box 80 - 11 x 9 x 6', 9, 11, 6, 0.4, 9, 11, 6, 60],
            ['Box 59 - 10 x 10 x 4', 10, 10, 4, 0.4, 10, 10, 4, 60],
            ['Box 55 - 10 x 9 x 6', 9, 10, 6, 0.4, 9, 10, 6, 60],
            ['Box 53 - 10 x 9 x 4', 9, 10, 4, 0.4, 9, 10, 4, 60],
            ['Box 51 - 10 x 8 x 7', 8, 10, 7, 0.4, 8, 10, 7, 60],
            ['Box 48 - 10 x 8 x 4', 8, 10, 4, 0.3, 8, 10, 4, 60],
            ['Box 44 - 10 x 7 x 4', 7, 10, 4, 0.3, 7, 10, 4, 60],
            ['Box 43 - 10 x 6 x 6', 6, 10, 6, 0.3, 6, 10, 6, 60],
            ['Box 33 - 9 x 8 x 7', 8, 9, 7, 0.4, 8, 9, 7, 60],
            ['Box 26 - 9 x 7 x 4', 7, 9, 4, 0.3, 7, 9, 4, 60],
            ['Box 18 - 8 x 8 x 4', 8, 8, 4, 0.3, 8, 8, 4, 60],
            ['Box 15 - 8 x 7 x 5', 7, 8, 5, 0.3, 7, 8, 5, 60],
            ['Box 12 - 8 x 6 x 5', 6, 8, 5, 0.2, 6, 8, 5, 60],
            ['Box 9 - 7 x 7 x 6', 7, 7, 6, 0.3, 7, 7, 6, 60],
            ['Box 8 - 7 x 7 x 5', 7, 7, 5, 0.3, 7, 7, 5, 60],
            ['Box 6 - 7 x 6 x 6', 6, 7, 6, 0.2, 6, 7, 6, 60],
            ['Box 5 - 7 x 6 x 5', 6, 7, 5, 0.2, 6, 7, 5, 60],
            ['Box 4 - 7 x 6 x 4', 6, 7, 4, 0.2, 6, 7, 4, 60],
            ['Box 3 - 6 x 6 x 6', 6, 6, 6, 0.2, 6, 6, 6, 60],
            ['Box 2 - 6 x 6 x 5', 6, 6, 5, 0.2, 6, 6, 5, 60],
            ['Box 1 - 6 x 6 x 4', 6, 6, 4, 0.2, 6, 6, 4, 60],
            ['PB1 - Plain box 22x12x6', 12, 22, 6, 0.85, 12, 22, 6, 60],
            ['W9 - Plain Box 16x12x8', 12, 16, 8, 0.952, 12, 16, 8, 60],
            ['W8 - Plain Box  14x11x11', 11, 14, 11, 1, 11, 14, 11, 60],
            ['W7 - Plain Box 8x5x3.5', 5, 8, 3.5, 0.2, 5, 8, 3.5, 60],
            ['W6 - Plain Box 22.5x16x10.5', 16, 22.5, 10.5, 2.14, 16, 22.5, 10.5, 60],
            ['W5 - Plain Box 16x10.5x15.25', 10.5, 16, 15.25, 1.2, 10.5, 16, 15.25, 60],
            ['W3 - Plain Box 12x12x16', 12, 12, 16, 1.3, 12, 12, 16, 60],
            ['W1 - Plain Box 11.5x9.5x3.5', 9.5, 11.5, 3.5, 0.42, 9.5, 11.5, 3.5, 60],
            ['W4 - Plain Box 16x8x8', 8, 16, 8, 0.614, 8, 16, 8, 60],
            ['W2 - Plain Box 12x6x6', 6, 12, 6, 0.5, 6, 12, 6, 60],
            ['A18 - LQ 5x5x8', 5.75, 5.75, 8.25, 0.3, 5.75, 5.75, 8.25, 60],
            ['A17 - LQ 8x5x8', 5.75, 8.25, 8.25, 0.35, 5.75, 8.25, 8.25, 60],
            ['A16 - LQ 13x13x13', 13, 13, 13, 1.2, 13, 13, 13, 60],
            ['Box 1683', 14, 22, 16, 2.246, 14, 22, 16, 60],
            ['Box 1684', 8, 28, 18, 1.382, 8, 28, 18, 60],
            ['Box 1685- 26x15x20', 15, 26, 20, 2.57, 15, 26, 20, 60],
            ['Box 1686- 14x10x36', 10, 14, 36, 2.222, 10, 14, 36, 60],
            ['Box 1682 - 22x6x15', 6, 22, 15, 1.048, 6, 22, 15, 60],
            ['Box 1681', 16, 16, 16, 1.75, 16, 16, 16, 60],
            ['A12 - LQ 28x12x9', 12, 28, 9, 1.4, 12, 28, 9, 60],
            ['A11 - LQ 22x10x6', 10, 22, 6, 0.9, 10, 22, 6, 60],
            ['A10 - LQ 21x15x6', 15, 21, 6, 1.45, 15, 21, 6, 60],
            ['A9 - LQ 18x12x10', 12, 18, 10, 1.35, 12, 18, 10, 60],
            ['A8 - LQ 16x12x8', 12, 16, 8, 0.95, 12, 16, 8, 60],
            ['A7 - LQ 16x8x8', 8, 16, 8, 0.8, 8, 16, 8, 60],
            ['A6 - LQ 15x6.5x4', 6.5, 15, 4, 0.4, 6.5, 15, 4, 60],
            ['A5 - LQ 14x11x11', 11, 14, 11, 0.95, 11, 14, 11, 60],
            ['A4 - LQ 13x9x7', 9, 13, 7, 0.65, 9, 13, 7, 60],
            ['A3 - LQ 12x12x8', 12, 12, 8, 0.9, 12, 12, 8, 60],
            ['A2 - LQ 10x8x6', 8, 10, 6, 0.45, 8, 10, 6, 60],
            ['A1 - LQ 8x5x3.5', 6, 8, 4, 0.2, 6, 8, 4, 60],
        ];
        foreach ($boxes as $box) {
            $packer->addBox(new TestBox($box[0], (int) ($box[1] * 100), (int) ($box[2] * 100), (int) ($box[3] * 100), (int) ($box[4] * 100), (int) ($box[5] * 100), (int) ($box[6] * 100), (int) ($box[7] * 100), (int) ($box[8] * 100)));
        }
        $items = [
            [7.2500, 15.0000, 6.5000, 42.8060, false, 1],
            [5.6250, 7.3125, 5.6875, 13.9880, false, 6],
            [6.1250, 14.7500, 6.5000, 50.0160, false, 3],
            [6.0000, 12.2500, 6.5000, 26.4200, false, 2],
            [9.7500, 12.0000, 5.0000, 37.4680, false, 4],
            [5.5000, 14.9500, 5.5000, 28.3210, false, 2],
            [2.7500, 4.7500, 1.7500, 1.8880, false, 1],
            [6.2500, 10.0000, 6.5000, 28.0420, false, 1],
            [6.1250, 6.5000, 6.3750, 11.3220, false, 1],
            [2.8750, 6.1250, 1.2500, 1.0280, false, 2],
        ];
        foreach ($items as $index => $item) {
            $packer->addItem(new TestItem('Item ' . ($index + 1), (int) ($item[0] * 100), (int) ($item[1] * 100), (int) ($item[2] * 100), (int) ($item[3] * 100 / 2), $item[4]), $item[5]);
        }
        $packedBoxList = $packer->pack();
        self::assertCount(6, $packedBoxList);
    }
}
