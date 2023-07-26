<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\ConstrainedPlacementByCountTestItem;
use DVDoug\BoxPacker\Test\ConstrainedPlacementNoStackingTestItem;
use DVDoug\BoxPacker\Test\ConstrainedTestItem;
use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\TestCase;

use function array_fill;

class VolumePackerTest extends TestCase
{
    /**
     * From issue #79.
     */
    public function testUsedDimensionsCalculatedCorrectly(): void
    {
        $box = new TestBox('Bundle', 75, 15, 15, 0, 75, 15, 15, 30);
        $itemList = new ItemList();
        $itemList->insert(new TestItem('Item 1', 14, 12, 2, 2, Rotation::KeepFlat));
        $itemList->insert(new TestItem('Item 2', 14, 12, 2, 2, Rotation::KeepFlat));
        $itemList->insert(new TestItem('Item 3', 14, 12, 2, 2, Rotation::KeepFlat));
        $itemList->insert(new TestItem('Item 4', 14, 12, 2, 2, Rotation::KeepFlat));
        $itemList->insert(new TestItem('Item 5', 14, 12, 2, 2, Rotation::KeepFlat));

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertEquals(60, $packedBox->getUsedWidth());
        self::assertEquals(14, $packedBox->getUsedLength());
        self::assertEquals(2, $packedBox->getUsedDepth());
    }

    /**
     * Test that constraint handling works correctly.
     */
    public function testLegacyConstraints(): void
    {
        // first a regular item
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 0));
        $packer->addItem(new TestItem('Item', 1, 1, 1, 0, Rotation::BestFit), 8);
        $packedBoxes = $packer->pack();

        self::assertCount(1, $packedBoxes);

        // same dimensions but now constrained by type
        ConstrainedTestItem::$limit = 2;

        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 0));
        $packer->addItem(new ConstrainedTestItem('Item', 1, 1, 1, 0, Rotation::BestFit), 8);
        $packedBoxes = $packer->pack();

        self::assertCount(4, $packedBoxes);
    }

    /**
     * Test that constraint handling works correctly.
     */
    public function testNewConstraintMatchesLegacy(): void
    {
        // first a regular item
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 0));
        $packer->addItem(new TestItem('Item', 1, 1, 1, 0, Rotation::BestFit), 8);
        $packedBoxes = $packer->pack();

        self::assertCount(1, $packedBoxes);

        // same dimensions but now constrained by type
        ConstrainedPlacementByCountTestItem::$limit = 2;

        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 0));
        $packer->addItem(new ConstrainedPlacementByCountTestItem('Item', 1, 1, 1, 0, Rotation::BestFit), 8);
        $packedBoxes = $packer->pack();

        self::assertCount(4, $packedBoxes);
    }

    /**
     * Test that constraint handling works correctly.
     */
    public function testNewConstraint(): void
    {
        // first a regular item
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 4, 1, 2, 0, 4, 1, 2, 0));
        $packer->addItem(new TestItem('Item', 1, 1, 1, 0, Rotation::BestFit), 8);
        $packedBoxes = $packer->pack();

        self::assertCount(1, $packedBoxes);

        // same dimensions but now constrained to not have stacking

        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 4, 1, 2, 0, 4, 1, 2, 0));
        $packer->addItem(new ConstrainedPlacementNoStackingTestItem('Item', 1, 1, 1, 0, Rotation::BestFit), 8);
        $packedBoxes = $packer->pack();

        self::assertCount(2, $packedBoxes);
    }

    /**
     * Test an infinite loop doesn't come back.
     */
    public function testIssue14(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('29x1x23Box', 29, 1, 23, 0, 29, 1, 23, 100));
        $packer->addItem(new TestItem('13x1x10Item', 13, 1, 10, 1, Rotation::KeepFlat));
        $packer->addItem(new TestItem('9x1x6Item', 9, 1, 6, 1, Rotation::KeepFlat));
        $packer->addItem(new TestItem('9x1x6Item', 9, 1, 6, 1, Rotation::KeepFlat));
        $packer->addItem(new TestItem('9x1x6Item', 9, 1, 6, 1, Rotation::KeepFlat));
        $packedBoxes = $packer->pack();

        self::assertCount(1, $packedBoxes);
    }

    /**
     * Test identical items keep their orientation (with box length > width).
     */
    public function testIssue47A(): void
    {
        $box = new TestBox('165x225x25Box', 165, 225, 25, 0, 165, 225, 25, 100);
        $item = new TestItem('20x69x20Item', 20, 69, 20, 0, Rotation::KeepFlat);
        $itemList = new ItemList();
        $itemList->insert($item, 23);

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
        $item = new TestItem('20x69x20Item', 69, 20, 20, 0, Rotation::KeepFlat);
        $itemList = new ItemList();
        $itemList->insert($item, 23);

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
        $item = new TestItem('30x10x30item', 30, 10, 30, 0, Rotation::KeepFlat);
        $itemList = new ItemList();
        $itemList->insert($item, 9);

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(9, $packedBox->getItems());
    }

    /**
     * From issue #124.
     */
    public function testUnpackedSpaceInsideLayersIsFilled(): void
    {
        $box = new TestBox('Box', 4, 14, 11, 0, 4, 14, 11, 100);
        $itemList = new ItemList();
        $itemList->insert(new TestItem('Item 1', 8, 8, 2, 1, Rotation::BestFit));
        $itemList->insert(new TestItem('Item 2', 4, 4, 4, 1, Rotation::BestFit));
        $itemList->insert(new TestItem('Item 3', 4, 4, 4, 1, Rotation::BestFit));

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(3, $packedBox->getItems());

        $box = new TestBox('Box', 14, 11, 4, 0, 14, 11, 4, 100);
        $itemList = new ItemList();
        $itemList->insert(new TestItem('Item 1', 8, 8, 2, 1, Rotation::BestFit));
        $itemList->insert(new TestItem('Item 2', 4, 4, 4, 1, Rotation::BestFit));
        $itemList->insert(new TestItem('Item 3', 4, 4, 4, 1, Rotation::BestFit));

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(3, $packedBox->getItems());
    }

    /**
     * Test stability of items is calculated appropriately.
     */
    public function testIssue148(): void
    {
        $box = new TestBox('Box', 27, 37, 22, 100, 25, 36, 21, 15000);
        $item = new TestItem('Item', 6, 12, 20, 100, Rotation::BestFit);
        $itemList = new ItemList();
        $itemList->insert($item, 12);

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(12, $packedBox->getItems());

        $box = new TestBox('Box', 27, 37, 22, 100, 25, 36, 21, 15000);
        $item = new TestItem('Item', 6, 12, 20, 100, Rotation::KeepFlat);
        $itemList = new ItemList();
        $itemList->insert($item, 12);

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
        $item = new TestItem('Item', 90, 200, 200, 150, Rotation::KeepFlat);
        $itemList->insert($item, 14);

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(14, $packedBox->getItems());

        // less efficient packing
        $packer->packAcrossWidthOnly();
        $packedBox = $packer->pack();

        self::assertCount(13, $packedBox->getItems());
    }

    /**
     * From issue #147.
     */
    public function testIssue147B(): void
    {
        $box = new TestBox('Box', 400, 200, 500, 0, 400, 200, 500, 10000);
        $itemList = new ItemList();
        $itemList->insert(new TestItem('Item 1', 447, 62, 303, 965, Rotation::BestFit));
        $itemList->insert(new TestItem('Item 2', 495, 70, 308, 1018, Rotation::BestFit));

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(2, $packedBox->getItems());

        $box = new TestBox('Box', 400, 200, 500, 0, 400, 200, 500, 10000);
        $itemList = new ItemList();
        $itemList->insert(new TestItem('Item 1', 447, 303, 62, 965, Rotation::BestFit));
        $itemList->insert(new TestItem('Item 2', 495, 308, 70, 1018, Rotation::BestFit));

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(2, $packedBox->getItems());
    }

    /**
     * From issue #161.
     */
    public function testIssue161(): void
    {
        $box = new TestBox('Box', 240, 150, 180, 0, 240, 150, 180, 10000);
        $item1 = new TestItem('Item 1', 70, 70, 95, 0, Rotation::BestFit);
        $item2 = new TestItem('Item 2', 95, 75, 95, 0, Rotation::KeepFlat);

        $itemList = new ItemList();
        $itemList->insert($item1, 6);
        $itemList->insert($item2, 3);

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();
        self::assertCount(9, $packedBox->getItems());

        $itemList = new ItemList();
        $itemList->insert($item1, 6);
        $itemList->insert($item2, 2);

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();
        self::assertCount(8, $packedBox->getItems());
    }

    /**
     * From issue #164.
     */
    public function testIssue164(): void
    {
        $box = new TestBox('Box', 820, 820, 830, 0, 820, 820, 830, 10000);

        $itemList = new ItemList();
        $itemList->insert(new TestItem('Item 1', 110, 110, 50, 100, Rotation::BestFit));
        $itemList->insert(new TestItem('Item 2', 100, 300, 30, 100, Rotation::BestFit));
        $itemList->insert(new TestItem('Item 3', 100, 150, 50, 100, Rotation::BestFit));
        $itemList->insert(new TestItem('Item 4', 100, 200, 80, 110, Rotation::BestFit));
        $itemList->insert(new TestItem('Item 5', 80, 150, 80, 50, Rotation::BestFit));
        $itemList->insert(new TestItem('Item 6', 80, 150, 80, 50, Rotation::BestFit));
        $itemList->insert(new TestItem('Item 7', 80, 150, 80, 50, Rotation::BestFit));
        $itemList->insert(new TestItem('Item 8', 270, 70, 60, 350, Rotation::BestFit));
        $itemList->insert(new TestItem('Item 9', 150, 150, 80, 180, Rotation::BestFit));
        $itemList->insert(new TestItem('Item 10', 80, 150, 80, 50, Rotation::BestFit));

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();
        self::assertCount(10, $packedBox->getItems());
    }

    /**
     * From issue #174.
     */
    public function testIssue174(): void
    {
        $box = new TestBox('Box', 0, 0, 0, 10, 5000, 5000, 5000, 10000);
        $items = new ItemList();

        $items->insert(new TestItem('Item 0', 1000, 1650, 850, 500, Rotation::BestFit));
        $items->insert(new TestItem('Item 1', 960, 1640, 800, 500, Rotation::BestFit));
        $items->insert(new TestItem('Item 2', 950, 1650, 800, 500, Rotation::BestFit));
        $items->insert(new TestItem('Item 3', 1000, 2050, 800, 500, Rotation::BestFit));
        $items->insert(new TestItem('Item 4', 1000, 2100, 850, 500, Rotation::BestFit));
        $items->insert(new TestItem('Item 5', 950, 2050, 800, 500, Rotation::BestFit));
        $items->insert(new TestItem('Item 6', 940, 970, 800, 500, Rotation::BestFit));

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(7, $packedBox->getItems());
    }

    /**
     * From issue #172.
     * @group efficiency
     */
    public function testIssue172A(): void
    {
        $box = new TestBox('Box', 800, 1200, 1300, 0, 800, 1200, 1300, 500000);
        $items = array_fill(0, 8928, new TestItem('Larger', 150, 110, 5, 56, Rotation::BestFit));

        $volumePacker = new VolumePacker($box, ItemList::fromArray($items, true));
        $packedBox = $volumePacker->pack();

        self::assertCount(8928, $packedBox->getItems());
    }

    /**
     * From issue #172.
     */
    public function testIssue172B(): void
    {
        $box = new TestBox('Box', 18, 18, 24, 0, 18, 18, 24, 10000);

        $items = new ItemList();
        $item = new TestItem('Larger', 10, 5, 8, 0, Rotation::BestFit);
        $items->insert($item, 10);

        $item = new TestItem('Smaller', 5, 5, 3, 0, Rotation::BestFit);
        $items->insert($item, 5);

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(15, $packedBox->getItems());
    }

    /**
     * From issue #186.
     */
    public function testPassedInItemListKeepsItems(): void
    {
        $box = new TestBox('Box', 18, 18, 24, 0, 18, 18, 24, 10000);

        $items = new ItemList();
        $item = new TestItem('Item', 10, 5, 8, 0, Rotation::BestFit);
        $items->insert($item, 10);

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(10, $items);
    }

    /**
     * From issue #190.
     */
    public function testOrientationDecisions(): void
    {
        $box = new TestBox('Box', 25, 25, 20, 0, 25, 25, 20, 1000);
        $items = new ItemList();
        $item = new TestItem('Item', 5, 6, 20, 20, Rotation::KeepFlat);
        $items->insert($item, 20);

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(20, $packedBox->getItems());

        $box = new TestBox('Box', 25, 25, 20, 0, 25, 25, 20, 1000);
        $items = new ItemList();
        $item = new TestItem('Item', 20, 5, 6, 20, Rotation::BestFit);
        $items->insert($item, 20);

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(20, $packedBox->getItems());
    }

    /**
     * From issue #175.
     */
    public function testIssue175(): void
    {
        $box = new TestBox('Box', 40, 40, 40, 0, 40, 40, 40, 1000);
        $items = new ItemList();
        $item = new TestItem('Item', 35, 35, 5, 20, Rotation::BestFit);
        $items->insert($item, 10);

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(10, $packedBox->getItems());
    }

    /**
     * From issue #221.
     */
    public function testIssue221(): void
    {
        $box = new TestBox('Box', 200, 200, 200, 0, 200, 200, 200, 1000);
        $items = new ItemList();
        $items->insert(new TestItem('Item 1', 150, 150, 150, 1, Rotation::BestFit));
        $item2 = new TestItem('Item 2', 50, 50, 50, 1, Rotation::BestFit);
        $items->insert($item2, 37);

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(38, $packedBox->getItems());
    }

    public function testIssue214(): void
    {
        $this->markTestSkipped();
        foreach ([Rotation::KeepFlat, Rotation::BestFit] as $rotation) {
            $box = new TestBox('A Box', 279, 215, 139, 10, 279, 215, 139, 100000);

            $items = new ItemList();
            $items->insert(new TestItem('Item A-1', 160, 160, 64, 1, $rotation));
            $items->insert(new TestItem('Item A-2', 160, 160, 64, 1, $rotation));
            $items->insert(new TestItem('Item B-1', 203, 114, 51, 1, $rotation));
            $items->insert(new TestItem('Item B-2', 203, 114, 51, 1, $rotation));

            $volumePacker = new VolumePacker($box, $items);
            $packedBox = $volumePacker->pack();

            self::assertCount(4, $packedBox->getItems());
        }
    }

    public function testIssue227(): void
    {
        $this->markTestSkipped();
        $box = new TestBox('Box', 160, 180, 160, 0, 160, 180, 160, 1000);

        $items = new ItemList();
        $items->insert(new TestItem('Item', 42, 100, 70, 1, Rotation::BestFit), 11);

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(4, $packedBox->getItems());
    }

    public function testIssue230(): void
    {
        $this->markTestSkipped();
        $box = new TestBox('Truck', 220, 210, 230, 0, 220, 210, 230, 1200);

        $items = new ItemList();
        $items->insert(new TestItem('Pallet', 80, 120, 140, 90, Rotation::KeepFlat), 4);

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(4, $packedBox->getItems());
    }

    public function testIssue240(): void
    {
        $this->markTestSkipped();
        $box = new TestBox('Le petite box', 220, 540, 1, 0, 220, 540, 1, 0);

        $items = new ItemList();
        $items->insert(new TestItem('1-60x80', 80, 60, 1, 0, Rotation::KeepFlat), 1);
        $items->insert(new TestItem('2-60x80', 80, 60, 1, 0, Rotation::KeepFlat), 1);
        $items->insert(new TestItem('3-60x100', 100, 60, 1, 0, Rotation::KeepFlat), 1);
        $items->insert(new TestItem('4-60x80', 80, 60, 1, 0, Rotation::KeepFlat), 1);
        $items->insert(new TestItem('5-60x80', 80, 60, 1, 0, Rotation::KeepFlat), 1);
        $items->insert(new TestItem('6-60x80', 80, 60, 1, 0, Rotation::KeepFlat), 1);
        $items->insert(new TestItem('7-60x80', 80, 60, 1, 0, Rotation::KeepFlat), 1);
        $items->insert(new TestItem('8-60x120', 120, 60, 1, 0, Rotation::KeepFlat), 1);
        $items->insert(new TestItem('9-60x160', 160, 60, 1, 0, Rotation::KeepFlat), 1);
        $items->insert(new TestItem('10-80x50', 50, 80, 1, 0, Rotation::KeepFlat), 1);
        $items->insert(new TestItem('11-80x60', 60, 80, 1, 0, Rotation::KeepFlat), 1);
        $items->insert(new TestItem('12-80x110', 110, 80, 1, 0, Rotation::KeepFlat), 1);
        $items->insert(new TestItem('13-90x160', 160, 90, 1, 0, Rotation::KeepFlat), 1);
        $items->insert(new TestItem('14-60x120', 120, 60, 1, 0, Rotation::KeepFlat), 1);
        $items->insert(new TestItem('15-60x80', 80, 60, 1, 0, Rotation::KeepFlat), 1);
        $items->insert(new TestItem('16-60x100', 100, 60, 1, 0, Rotation::KeepFlat), 1);
        $items->insert(new TestItem('17-60x120', 120, 60, 1, 0, Rotation::KeepFlat), 1);
        $items->insert(new TestItem('18-60x120', 120, 60, 1, 0, Rotation::KeepFlat), 1);

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(18, $packedBox->getItems());
    }

    public function testIssue264(): void
    {
        foreach ([Rotation::KeepFlat, Rotation::BestFit] as $rotation) {
            $this->markTestSkipped();
            $box = new TestBox('Small', 160, 130, 70, 0, 160, 130, 70, 100000);

            $items = new ItemList();
            $items->insert(new TestItem('Item 1', 105, 70, 14, 0, $rotation), 1);
            $items->insert(new TestItem('Item 2', 152, 101, 5, 0, $rotation), 1);
            $items->insert(new TestItem('Item 3', 80, 70, 50, 0, $rotation), 1);
            $items->insert(new TestItem('Item 4', 97, 71, 28, 0, $rotation), 1);
            $items->insert(new TestItem('Item 5', 95, 70, 28, 0, $rotation), 1);

            $volumePacker = new VolumePacker($box, $items);
            $packedBox = $volumePacker->pack();

            self::assertCount(5, $packedBox->getItems());
        }
    }

    public function testIssue268(): void
    {
        foreach ([Rotation::KeepFlat, Rotation::BestFit] as $rotation) {
            $this->markTestSkipped();
            $box = new TestBox('Box', 280, 175, 180, 0, 280, 175, 180, 100000);

            $items = new ItemList();
            $items->insert(new TestItem('Item', 140, 35, 30, 0, $rotation), 60);

            $volumePacker = new VolumePacker($box, $items);
            $packedBox = $volumePacker->pack();

            self::assertCount(60, $packedBox->getItems());
        }
    }

    public function testIssue272(): void
    {
        $this->markTestSkipped();
        $box = new TestBox('Box', 725, 725, 650, 0, 725, 725, 650, 100000);

        $items = new ItemList();
        $items->insert(new TestItem('Item', 260, 260, 460, 0, Rotation::BestFit), 6);

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(6, $packedBox->getItems());
    }

    public function testIssue348(): void
    {
        $box = new TestBox('18x14x8', 180, 140, 80, 0, 180, 140, 80, 100);

        $items = new ItemList();
        $items->insert(new TestItem('Product1', 45, 20, 35, 17, Rotation::BestFit), 2);
        $items->insert(new TestItem('Product2', 175, 70, 70, 26, Rotation::BestFit), 1);
        $items->insert(new TestItem('Product3', 155, 70, 70, 20, Rotation::BestFit), 1);

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(4, $packedBox->getItems());
    }

    public function testIssue366(): void
    {
        $this->markTestSkipped();
        $box = new TestBox('Pallet', 250, 160, 1, 0, 250, 160, 1, 100);

        $items = new ItemList();
        $items->insert(new TestItem('Product1', 30, 70, 1, 1, Rotation::BestFit), 18);

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(18, $packedBox->getItems());
    }

    public function testIssue465A(): void
    {
        $box = new TestBox('Container', 60, 90, 1, 0, 60, 90, 1, 0);
        $t = new TestItem('T', 30, 60, 1, 0, Rotation::KeepFlat);
        $x = new TestItem('X', 30, 30, 1, 0, Rotation::KeepFlat);
        $l = new TestItem('L', 15, 30, 1, 0, Rotation::KeepFlat);
        $s = new TestItem('S', 15, 30, 1, 0, Rotation::KeepFlat);

        $items = new ItemList();
        $items->insert($t);
        $items->insert($t);
        $items->insert($x);
        $items->insert($l);
        $items->insert($s);

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(5, $packedBox->getItems());
    }

    public function testIssue465B(): void
    {
        $box = new TestBox('Container', 60, 90, 1, 0, 60, 90, 1, 0);
        $h = new TestItem('H', 45, 60, 1, 0, Rotation::KeepFlat);
        $q = new TestItem('Q', 45, 30, 1, 0, Rotation::KeepFlat);
        $l = new TestItem('L', 15, 30, 1, 0, Rotation::KeepFlat);

        $items = new ItemList();
        $items->insert($h);
        $items->insert($q);
        $items->insert($l);
        $items->insert($l);

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(4, $packedBox->getItems());
    }

    public function testIssue465C(): void
    {
        $box = new TestBox('Container', 60, 90, 1, 0, 60, 90, 1, 0);
        $h = new TestItem('H', 45, 60, 1, 0, Rotation::KeepFlat);
        $q = new TestItem('Q', 45, 30, 1, 0, Rotation::KeepFlat);
        $x = new TestItem('X', 30, 30, 1, 0, Rotation::KeepFlat);
        $l = new TestItem('L', 15, 30, 1, 0, Rotation::KeepFlat);

        $items = new ItemList();
        $items->insert($h);
        $items->insert($q);
        $items->insert($x);
        $items->insert($l);

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(4, $packedBox->getItems());
    }
}
