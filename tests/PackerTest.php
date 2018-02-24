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
 * @covers \DVDoug\BoxPacker\Packer
 */
class PackerTest extends TestCase
{
    /**
     * @expectedException \DVDoug\BoxPacker\ItemTooLargeException
     */
    public function testPackThreeItemsOneDoesntFitInAnyBox()
    {
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

        self::assertEquals(2, $packedBoxes[0]->getItems()->count());
        self::assertEquals(2, $packedBoxes[1]->getItems()->count());

        // same items, but with redistribution turned off - expecting 3+1 based on pure fit
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 1, 1, 3, 0, 1, 1, 3, 3));
        $packer->addItem(new TestItem('Item', 1, 1, 1, 1, false), 4);
        $packer->setMaxBoxesToBalanceWeight(1);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertEquals(3, $packedBoxes[0]->getItems()->count());
        self::assertEquals(1, $packedBoxes[1]->getItems()->count());
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

        self::assertEquals(1, $packedBoxes->count());
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

        self::assertEquals(1, $packedBoxes->count());
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

        self::assertEquals(1, count($packedBoxes));

        self::assertEquals(210, $packedBoxes[0]->getUsedWidth());
        self::assertEquals(297, $packedBoxes[0]->getUsedLength());
        self::assertEquals(74, $packedBoxes[0]->getUsedDepth());
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

        self::assertEquals(2, count($packedBoxes));
    }
}
