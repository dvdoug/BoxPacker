<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\ConstrainedPlacementByCountTestItem;
use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\TestCase;

class PackerTest extends TestCase
{
    /**
     * @expectedException \DVDoug\BoxPacker\ItemTooLargeException
     */
    public function testPackThreeItemsOneDoesntFitInAnyBox()
    {
        if (method_exists($this, 'expectException')) {
            $this->expectException('\DVDoug\BoxPacker\ItemTooLargeException');
        }
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

    /**
     * @expectedException \DVDoug\BoxPacker\ItemTooLargeException
     */
    public function testPackWithoutBox()
    {
        if (method_exists($this, 'expectException')) {
            $this->expectException('\DVDoug\BoxPacker\ItemTooLargeException');
        }
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
    public function testCanSetMaxBoxesToWeightBalance()
    {
        $packer = new Packer();
        $packer->setMaxBoxesToBalanceWeight(3);
        self::assertEquals(3, $packer->getMaxBoxesToBalanceWeight());
    }

    /**
     * Test that weight redistribution activates (or not) correctly based on the current limit.
     */
    public function testWeightRedistributionActivatesOrNot()
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
    public function testIssue52A()
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
    public function testIssue52B()
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
    public function testIssue52C()
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
    public function testIssue117()
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
    public function testIssue38()
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
    public function testIssue168()
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
    public function testIssue170()
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
    public function testIssue182A()
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
     * From issue #191.
     */
    public function testIssue191()
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
    public function testIssue192()
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
    public function testIssue196()
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
    public function testNumberOfBoxesTorture()
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

        $packer->addItem(new TestItem('Item 1', 725, 1500, 650, 21403, false), 1);
        $packer->addItem(new TestItem('Item 2', 562, 731, 568, 6994, false), 6);
        $packer->addItem(new TestItem('Item 3', 612, 1475, 650, 25008, false), 3);
        $packer->addItem(new TestItem('Item 4', 600, 1225, 650, 13210, false), 2);
        $packer->addItem(new TestItem('Item 5', 975, 1200, 500, 18734, false), 4);
        $packer->addItem(new TestItem('Item 6', 550, 1495, 550, 14160, false), 2);
        $packer->addItem(new TestItem('Item 7', 275, 475, 175, 944, false), 1);
        $packer->addItem(new TestItem('Item 8', 625, 1000, 650, 14021, false), 1);
        $packer->addItem(new TestItem('Item 9', 612, 650, 637, 5661, false), 1);
        $packer->addItem(new TestItem('Item 10', 287, 612, 125, 514, false), 2);

        $packedBoxList = $packer->pack();
        self::assertCount(6, $packedBoxList);
    }

    public function testIssue206()
    {
        ConstrainedPlacementByCountTestItem::$limit = 2;
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 6, 10, 6, 0, 6, 10, 5, PHP_INT_MAX));
        $packer->addItem(new ConstrainedPlacementByCountTestItem('ConstrainedItem', 1, 1, 1, 1, false), 3);
        $packer->addItem(new TestItem('RegularItem', 2, 4, 1, 2, false), 5);
        $packedBoxes = $packer->pack();

        self::assertCount(2, $packedBoxes);
    }
}
