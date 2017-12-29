<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\TestCase;

class PackerTest extends TestCase
{
    public function testPackThreeItemsFitEasilyInSmallerOfTwoBoxes()
    {
        $box1 = new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000);
        $box2 = new TestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000);

        $item1 = new TestItem('Item 1', 250, 250, 2, 200, true);
        $item2 = new TestItem('Item 2', 250, 250, 2, 200, true);
        $item3 = new TestItem('Item 3', 250, 250, 2, 200, true);

        $packer = new Packer();
        $packer->addBox($box1);
        $packer->addBox($box2);
        $packer->addItem($item1);
        $packer->addItem($item2);
        $packer->addItem($item3);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertEquals(1, count($packedBoxes));
        self::assertEquals(3, $packedBoxes[0]->getItems()->count());
        self::assertEquals($box1, $packedBoxes[0]->getBox());
        self::assertEquals(610, $packedBoxes[0]->getWeight());
    }

    public function testPackThreeItemsFitEasilyInLargerOfTwoBoxes()
    {
        $box1 = new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000);
        $box2 = new TestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000);

        $item1 = new TestItem('Item 1', 2500, 2500, 20, 2000, true);
        $item2 = new TestItem('Item 2', 2500, 2500, 20, 2000, true);
        $item3 = new TestItem('Item 3', 2500, 2500, 20, 2000, true);

        $packer = new Packer();
        $packer->addBox($box1);
        $packer->addBox($box2);
        $packer->addItem($item1);
        $packer->addItem($item2);
        $packer->addItem($item3);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertEquals(1, count($packedBoxes));
        self::assertEquals(3, $packedBoxes[0]->getItems()->count());
        self::assertEquals($box2, $packedBoxes[0]->getBox());
        self::assertEquals(6100, $packedBoxes[0]->getWeight());
    }

    public function testPackFiveItemsTwoLargeOneSmallBox()
    {
        $box1 = new TestBox('Le petite box', 600, 600, 10, 10, 596, 596, 8, 1000);
        $box2 = new TestBox('Le grande box', 3000, 3000, 50, 100, 2960, 2960, 40, 10000);

        $item1 = new TestItem('Item 1', 2500, 2500, 20, 500, true);
        $item2 = new TestItem('Item 2', 550, 550, 2, 500, true);
        $item3 = new TestItem('Item 3', 2500, 2500, 20, 500, true);
        $item4 = new TestItem('Item 4', 2500, 2500, 20, 500, true);
        $item5 = new TestItem('Item 5', 2500, 2500, 20, 500, true);

        $packer = new Packer();
        $packer->addBox($box1);
        $packer->addBox($box2);
        $packer->addItem($item1);
        $packer->addItem($item2);
        $packer->addItem($item3);
        $packer->addItem($item4);
        $packer->addItem($item5);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertEquals(3, count($packedBoxes));

        self::assertEquals(2, $packedBoxes[0]->getItems()->count());
        self::assertEquals($box2, $packedBoxes[0]->getBox());
        self::assertEquals(1100, $packedBoxes[0]->getWeight());

        self::assertEquals(2, $packedBoxes[1]->getItems()->count());
        self::assertEquals($box2, $packedBoxes[1]->getBox());
        self::assertEquals(1100, $packedBoxes[1]->getWeight());

        self::assertEquals(1, $packedBoxes[2]->getItems()->count());
        self::assertEquals($box1, $packedBoxes[2]->getBox());
        self::assertEquals(510, $packedBoxes[2]->getWeight());
    }

    public function testPackFiveItemsTwoLargeOneSmallBoxButThreeAfterRepack()
    {
        $box1 = new TestBox('Le petite box', 600, 600, 10, 10, 596, 596, 8, 1000);
        $box2 = new TestBox('Le grande box', 3000, 3000, 50, 100, 2960, 2960, 40, 10000);

        $item1 = new TestItem('Item 1', 2500, 2500, 20, 2000, true);
        $item2 = new TestItem('Item 2', 550, 550, 2, 200, true);
        $item3 = new TestItem('Item 3', 2500, 2500, 20, 2000, true);
        $item4 = new TestItem('Item 4', 2500, 2500, 20, 2000, true);
        $item5 = new TestItem('Item 5', 2500, 2500, 20, 2000, true);

        $packer = new Packer();
        $packer->addBox($box1);
        $packer->addBox($box2);
        $packer->addItem($item1);
        $packer->addItem($item2);
        $packer->addItem($item3);
        $packer->addItem($item4);
        $packer->addItem($item5);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertEquals(3, count($packedBoxes));

        self::assertEquals(2, $packedBoxes[0]->getItems()->count());
        self::assertEquals($box2, $packedBoxes[0]->getBox());
        self::assertEquals(2300, $packedBoxes[0]->getWeight());

        self::assertEquals(2, $packedBoxes[1]->getItems()->count());
        self::assertEquals($box2, $packedBoxes[1]->getBox());
        self::assertEquals(4100, $packedBoxes[1]->getWeight());

        self::assertEquals(1, $packedBoxes[2]->getItems()->count());
        self::assertEquals($box2, $packedBoxes[2]->getBox());
        self::assertEquals(2100, $packedBoxes[2]->getWeight());
    }

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
        $packedBoxes = $packer->pack();
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
        $packedBoxes = $packer->pack();
    }

    public function testIssue1()
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Le petite box', 292, 336, 60, 10, 292, 336, 60, 9000));
        $packer->addBox(new TestBox('Le grande box', 421, 548, 335, 100, 421, 548, 335, 10000));
        $packer->addItem(new TestItem('Item 1', 226, 200, 40, 440, true));
        $packer->addItem(new TestItem('Item 2', 200, 200, 155, 1660, true));
        $packedBoxes = $packer->pack();

        self::assertEquals(1, $packedBoxes->count());
    }

    public function testIssue3()
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('OW Box 1', 51, 33, 33, 1, 51, 33, 33, 1));
        $packer->addBox(new TestBox('OW Box 2', 50, 40, 40, 1, 50, 40, 40, 1));
        $packer->addItem(new TestItem('Product', 28, 19, 9, 0, true), 6);
        $packedBoxes = $packer->pack();

        self::assertEquals(1, $packedBoxes->count());
    }

    public function testIssue9()
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('24x24x24Box', 24, 24, 24, 24, 24, 24, 24, 100));

        $packer->addItem(new TestItem('6x6x6Item', 6, 6, 6, 1, true), 64);
        $packedBoxes = $packer->pack();

        self::assertEquals(1, $packedBoxes->count());
    }

    public function testIssue11()
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('4x4x4Box', 4, 4, 4, 4, 4, 4, 4, 100));

        $packer->addItem(new TestItem('BigItem', 2, 2, 4, 1, true), 2);
        $packer->addItem(new TestItem('SmallItem', 1, 1, 1, 1, true), 32);
        $packedBoxes = $packer->pack();

        self::assertEquals(1, $packedBoxes->count());
    }

    public function testIssue13()
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Le petite box', 12, 12, 12, 10, 10, 10, 10, 1000));

        $packer->addItem(new TestItem('Item 1', 5, 3, 2, 2, true));
        $packer->addItem(new TestItem('Item 2', 5, 3, 2, 2, true));
        $packer->addItem(new TestItem('Item 3', 3, 3, 3, 3, true));
        $packedBoxes = $packer->pack();

        self::assertEquals(1, $packedBoxes->count());
    }

    public function testIssue14()
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('29x1x23Box', 29, 1, 23, 0, 29, 1, 23, 100));
        $packer->addItem(new TestItem('13x1x10Item', 13, 1, 10, 1, true));
        $packer->addItem(new TestItem('9x1x6Item', 9, 1, 6, 1, true));
        $packer->addItem(new TestItem('9x1x6Item', 9, 1, 6, 1, true));
        $packer->addItem(new TestItem('9x1x6Item', 9, 1, 6, 1, true));
        $packedBoxes = $packer->pack();

        self::assertEquals(1, $packedBoxes->count());
    }

    public function testIssue47A()
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('165x225x25Box', 165, 225, 25, 0, 165, 225, 25, 100));
        $packer->addItem(new TestItem('20x69x20Item', 20, 69, 20, 0, true), 23);
        $packedBoxes = $packer->pack();

        self::assertEquals(1, $packedBoxes->count());
    }

    public function testIssue47B()
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('165x225x25Box', 165, 225, 25, 0, 165, 225, 25, 100));
        $packer->addItem(new TestItem('20x69x20Item', 69, 20, 20, 0, true), 23);
        $packedBoxes = $packer->pack();

        self::assertEquals(1, $packedBoxes->count());
    }

    public function testPackerPacksRotatedBoxesInNewRow()
    {
        $packer = new Packer();
        $packer->addItem(new TestItem('30x10x30item', 30, 10, 30, 0, true), 9);

        //Box can hold 7 items in a row and then is completely full, so 9 items won't fit
        $packer->addBox(new TestBox('30x70x30InternalBox', 30, 70, 30, 0, 30, 70, 30, 1000));
        $packedBoxes = $packer->pack();
        self::assertEquals(2, $packedBoxes->count());

        //Box can hold 7 items in a row, plus two more rotated, making 9 items
        // with a 10x10x30 hole in the corner.
        //
        // Overhead view:
        //
        // +--+--++
        // ++++++++
        // ||||||||
        // ++++++++
        //
        $packer = new Packer();
        $packer->addItem(new TestItem('30x10x30item', 30, 10, 30, 0, true), 9);
        $packer->addBox(new TestBox('40x70x30InternalBox', 40, 70, 30, 0, 40, 70, 30, 1000));
        $packedBoxes = $packer->pack();
        self::assertEquals(1, $packedBoxes->count());

        // Make sure that it doesn't try to fit in a 10th item
        $packer = new Packer();
        $packer->addItem(new TestItem('30x10x30item', 30, 10, 30, 0, true), 10);
        $packer->addBox(new TestBox('40x70x30InternalBox', 40, 70, 30, 0, 40, 70, 30, 1000));
        $packedBoxes = $packer->pack();
        self::assertEquals(2, $packedBoxes->count());
    }

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

    public function testIssue52C()
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 230, 300, 240, 160, 230, 300, 240, 15000));
        $packer->addItem(new TestItem('Item 1', 210, 297, 4, 213, true));
        $packer->addItem(new TestItem('Item 2', 80, 285, 70, 199, true));
        $packer->addItem(new TestItem('Item 3', 80, 285, 70, 199, true));

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertEquals(2, count($packedBoxes));

        self::assertEquals(160, $packedBoxes[0]->getUsedWidth());
        self::assertEquals(285, $packedBoxes[0]->getUsedLength());
        self::assertEquals(70, $packedBoxes[0]->getUsedDepth());

        self::assertEquals(210, $packedBoxes[1]->getUsedWidth());
        self::assertEquals(297, $packedBoxes[1]->getUsedLength());
        self::assertEquals(4, $packedBoxes[1]->getUsedDepth());
    }

    public function testIssue79()
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Bundle', 75, 15, 15, 0, 75, 15, 15, 30));
        $packer->addItem(new TestItem('Item 1', 14, 12, 2, 2, true));
        $packer->addItem(new TestItem('Item 2', 14, 12, 2, 2, true));
        $packer->addItem(new TestItem('Item 3', 14, 12, 2, 2, true));
        $packer->addItem(new TestItem('Item 4', 14, 12, 2, 2, true));
        $packer->addItem(new TestItem('Item 5', 14, 12, 2, 2, true));

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertEquals(60, $packedBoxes[0]->getUsedWidth());
        self::assertEquals(14, $packedBoxes[0]->getUsedLength());
        self::assertEquals(2, $packedBoxes[0]->getUsedDepth());
    }

    public function testCanSetMaxBoxesToWeightBalance()
    {
        $packer = new Packer();
        $packer->setMaxBoxesToBalanceWeight(3);
        self::assertEquals(3, $packer->getMaxBoxesToBalanceWeight());
    }

    public function testWeightRedistributionActivatesUnderLimit()
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 1, 1, 3, 0, 1, 1, 3, 3));
        $packer->addItem(new TestItem('Item', 1, 1, 1, 1, false), 4);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertEquals(2, $packedBoxes[0]->getItems()->count());
        self::assertEquals(2, $packedBoxes[1]->getItems()->count());
    }

    public function testWeightRedistributionDoesNotActivateOverLimit()
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 1, 1, 3, 0, 1, 1, 3, 3));
        $packer->addItem(new TestItem('Item', 1, 1, 1, 1, false), 4);
        $packer->setMaxBoxesToBalanceWeight(1);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertEquals(3, $packedBoxes[0]->getItems()->count());
        self::assertEquals(1, $packedBoxes[1]->getItems()->count());
    }
}
