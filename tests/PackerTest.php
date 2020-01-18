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
}
