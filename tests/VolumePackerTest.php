<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestConstrainedTestItem;
use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DVDoug\BoxPacker\VolumePacker
 */
class VolumePackerTest extends TestCase
{
    /**
     * From issue #79.
     */
    public function testUsedDimensionsCalculatedCorrectly(): void
    {
        $box = new TestBox('Bundle', 75, 15, 15, 0, 75, 15, 15, 30);
        $itemList = new ItemList();
        $itemList->insert(new TestItem('Item 1', 14, 12, 2, 2, true));
        $itemList->insert(new TestItem('Item 2', 14, 12, 2, 2, true));
        $itemList->insert(new TestItem('Item 3', 14, 12, 2, 2, true));
        $itemList->insert(new TestItem('Item 4', 14, 12, 2, 2, true));
        $itemList->insert(new TestItem('Item 5', 14, 12, 2, 2, true));

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertEquals(60, $packedBox->getUsedWidth());
        self::assertEquals(14, $packedBox->getUsedLength());
        self::assertEquals(2, $packedBox->getUsedDepth());
    }

    /**
     * From issue #86.
     */
    public function testUsedWidthAndRemainingWidthHandleRotationsCorrectly(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 23, 27, 14, 0, 23, 27, 14, 30));
        $packer->addItem(new TestItem('Item 1', 11, 22, 2, 1, true), 3);
        $packer->addItem(new TestItem('Item 2', 11, 22, 2, 1, true), 4);
        $packer->addItem(new TestItem('Item 3', 6, 17, 2, 1, true), 3);
        $packedBoxes = $packer->pack();

        self::assertCount(1, $packedBoxes);

        /** @var PackedBox $packedBox */
        $packedBox = $packedBoxes->top();
        self::assertEquals(22, $packedBox->getUsedWidth());
        self::assertEquals(23, $packedBox->getUsedLength());
        self::assertEquals(10, $packedBox->getUsedDepth());
        self::assertEquals(1, $packedBox->getRemainingWidth());
        self::assertEquals(4, $packedBox->getRemainingLength());
        self::assertEquals(4, $packedBox->getRemainingDepth());
    }

    /**
     * Test that constraint handling works correctly.
     */
    public function testConstraints(): void
    {
        // first a regular item
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 0));
        $packer->addItem(new TestItem('Item', 1, 1, 1, 0, false), 8);
        $packedBoxes = $packer->pack();

        self::assertCount(1, $packedBoxes);

        // same dimensions but now constrained by type
        TestConstrainedTestItem::$limit = 2;

        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 0));
        $packer->addItem(new TestConstrainedTestItem('Item', 1, 1, 1, 0, false), 8);
        $packedBoxes = $packer->pack();

        self::assertCount(4, $packedBoxes);
    }

    /**
     * Test an infinite loop doesn't come back.
     */
    public function testIssue14(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('29x1x23Box', 29, 1, 23, 0, 29, 1, 23, 100));
        $packer->addItem(new TestItem('13x1x10Item', 13, 1, 10, 1, true));
        $packer->addItem(new TestItem('9x1x6Item', 9, 1, 6, 1, true));
        $packer->addItem(new TestItem('9x1x6Item', 9, 1, 6, 1, true));
        $packer->addItem(new TestItem('9x1x6Item', 9, 1, 6, 1, true));
        $packedBoxes = $packer->pack();

        self::assertCount(1, $packedBoxes);
    }

    /**
     * Test identical items keep their orientation (with box length > width).
     */
    public function testIssue47A(): void
    {
        $box = new TestBox('165x225x25Box', 165, 225, 25, 0, 165, 225, 25, 100);
        $item = new TestItem('20x69x20Item', 20, 69, 20, 0, true);
        $itemList = new ItemList();
        for ($i = 0; $i < 23; $i++) {
            $itemList->insert($item);
        }

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(23, $packedBox->getItems());
    }

    /**
     * Test identical items keep their orientation (with box length < width).
     */
    public function testIssue47B(): void
    {
        $box = new TestBox('165x225x25Box', 165, 225, 25, 0, 165, 225, 25, 100);
        $item = new TestItem('20x69x20Item', 69, 20, 20, 0, true);
        $itemList = new ItemList();
        for ($i = 0; $i < 23; $i++) {
            $itemList->insert($item);
        }

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(23, $packedBox->getItems());
    }

    /**
     * Test that identical orientation doesn't survive change of row
     * (7 side by side, then 2 side by side rotated).
     */
    public function testAllowsRotatedBoxesInNewRow(): void
    {
        $box = new TestBox('40x70x30InternalBox', 40, 70, 30, 0, 40, 70, 30, 1000);
        $item = new TestItem('30x10x30item', 30, 10, 30, 0, true);
        $itemList = new ItemList();
        for ($i = 0; $i < 9; $i++) {
            $itemList->insert($item);
        }

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(9, $packedBox->getItems());
    }

    /**
     * Test stability of items is calculated appropriately.
     */
    public function testIssue148(): void
    {
        $box = new TestBox('Box', 27, 37, 22, 100, 25, 36, 21, 15000);
        $item = new TestItem('Item', 6, 12, 20, 100, false);
        $itemList = new ItemList();
        for ($i = 0; $i < 12; $i++) {
            $itemList->insert($item);
        }

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(12, $packedBox->getItems());

        $box = new TestBox('Box', 27, 37, 22, 100, 25, 36, 21, 15000);
        $item = new TestItem('Item', 6, 12, 20, 100, true);
        $itemList = new ItemList();
        for ($i = 0; $i < 12; $i++) {
            $itemList->insert($item);
        }

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(12, $packedBox->getItems());
    }

    /**
     * From issue #147.
     */
    public function testIssue147A(): void
    {
        $box = new TestBox('Box', 250, 1360, 260, 0, 250, 1360, 260, 30000);
        $itemList = new ItemList();
        $item = new TestItem('Item', 90, 200, 200, 150, true);
        for ($i = 0; $i < 14; $i++) {
            $itemList->insert($item);
        }
        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(14, $packedBox->getItems());
    }


    /**
     * From issue #147.
     */
    public function testIssue147B(): void
    {
        $box = new TestBox('Box', 400, 200, 500, 0, 400, 200, 500, 10000);
        $itemList = new ItemList();
        $itemList->insert(new TestItem('Item 1', 447, 62, 303, 965, false));
        $itemList->insert(new TestItem('Item 2', 495, 70, 308, 1018, false));

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(2, $packedBox->getItems());

        $box = new TestBox('Box', 400, 200, 500, 0, 400, 200, 500, 10000);
        $itemList = new ItemList();
        $itemList->insert(new TestItem('Item 1', 447, 303, 62, 965, false));
        $itemList->insert(new TestItem('Item 2', 495, 308, 70, 1018, false));

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(2, $packedBox->getItems());
    }
}
