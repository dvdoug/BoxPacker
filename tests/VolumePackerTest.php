<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\ConstrainedPlacementByCountTestItem;
use DVDoug\BoxPacker\Test\ConstrainedPlacementNoStackingTestItem;
use DVDoug\BoxPacker\Test\ConstrainedTestItem;
use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\TestCase;

class VolumePackerTest extends TestCase
{
    /**
     * From issue #79.
     */
    public function testUsedDimensionsCalculatedCorrectly()
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
     * Test that constraint handling works correctly.
     */
    public function testLegacyConstraints()
    {
        // first a regular item
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 0));
        $packer->addItem(new TestItem('Item', 1, 1, 1, 0, false), 8);
        $packedBoxes = $packer->pack();

        self::assertCount(1, $packedBoxes);

        // same dimensions but now constrained by type
        ConstrainedTestItem::$limit = 2;

        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 0));
        $packer->addItem(new ConstrainedTestItem('Item', 1, 1, 1, 0, false), 8);
        $packedBoxes = $packer->pack();

        self::assertCount(4, $packedBoxes);
    }

    /**
     * Test that constraint handling works correctly.
     */
    public function testNewConstraintMatchesLegacy()
    {
        // first a regular item
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 0));
        $packer->addItem(new TestItem('Item', 1, 1, 1, 0, false), 8);
        $packedBoxes = $packer->pack();

        self::assertCount(1, $packedBoxes);

        // same dimensions but now constrained by type
        ConstrainedPlacementByCountTestItem::$limit = 2;

        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 10, 10, 10, 0, 10, 10, 10, 0));
        $packer->addItem(new ConstrainedPlacementByCountTestItem('Item', 1, 1, 1, 0, false), 8);
        $packedBoxes = $packer->pack();

        self::assertCount(4, $packedBoxes);
    }

    /**
     * Test that constraint handling works correctly.
     */
    public function testNewConstraint()
    {
        // first a regular item
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 4, 1, 2, 0, 4, 1, 2, 0));
        $packer->addItem(new TestItem('Item', 1, 1, 1, 0, false), 8);
        $packedBoxes = $packer->pack();

        self::assertCount(1, $packedBoxes);

        // same dimensions but now constrained to not have stacking

        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 4, 1, 2, 0, 4, 1, 2, 0));
        $packer->addItem(new ConstrainedPlacementNoStackingTestItem('Item', 1, 1, 1, 0, false), 8);
        $packedBoxes = $packer->pack();

        self::assertCount(2, $packedBoxes);
    }

    /**
     * Test an infinite loop doesn't come back.
     */
    public function testIssue14()
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
    public function testIssue47A()
    {
        $box = new TestBox('165x225x25Box', 165, 225, 25, 0, 165, 225, 25, 100);
        $item = new TestItem('20x69x20Item', 20, 69, 20, 0, true);
        $itemList = new ItemList();
        for ($i = 0; $i < 23; ++$i) {
            $itemList->insert($item);
        }

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(23, $packedBox->getItems());
    }

    /**
     * Test identical items keep their orientation (with box length < width).
     */
    public function testIssue47B()
    {
        $box = new TestBox('165x225x25Box', 165, 225, 25, 0, 165, 225, 25, 100);
        $item = new TestItem('20x69x20Item', 69, 20, 20, 0, true);
        $itemList = new ItemList();
        for ($i = 0; $i < 23; ++$i) {
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
    public function testAllowsRotatedBoxesInNewRow()
    {
        $box = new TestBox('40x70x30InternalBox', 40, 70, 30, 0, 40, 70, 30, 1000);
        $item = new TestItem('30x10x30item', 30, 10, 30, 0, true);
        $itemList = new ItemList();
        for ($i = 0; $i < 9; ++$i) {
            $itemList->insert($item);
        }

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(9, $packedBox->getItems());
    }

    /**
     * From issue #124.
     */
    public function testUnpackedSpaceInsideLayersIsFilled()
    {
        $box = new TestBox('Box', 4, 14, 11, 0, 4, 14, 11, 100);
        $itemList = new ItemList();
        $itemList->insert(new TestItem('Item 1', 8, 8, 2, 1, false));
        $itemList->insert(new TestItem('Item 2', 4, 4, 4, 1, false));
        $itemList->insert(new TestItem('Item 3', 4, 4, 4, 1, false));

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(3, $packedBox->getItems());

        $box = new TestBox('Box', 14, 11, 4, 0, 14, 11, 4, 100);
        $itemList = new ItemList();
        $itemList->insert(new TestItem('Item 1', 8, 8, 2, 1, false));
        $itemList->insert(new TestItem('Item 2', 4, 4, 4, 1, false));
        $itemList->insert(new TestItem('Item 3', 4, 4, 4, 1, false));

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(3, $packedBox->getItems());
    }

    /**
     * Test stability of items is calculated appropriately.
     */
    public function testIssue148()
    {
        $box = new TestBox('Box', 27, 37, 22, 100, 25, 36, 21, 15000);
        $item = new TestItem('Item', 6, 12, 20, 100, false);
        $itemList = new ItemList();
        for ($i = 0; $i < 12; ++$i) {
            $itemList->insert($item);
        }

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(12, $packedBox->getItems());

        $box = new TestBox('Box', 27, 37, 22, 100, 25, 36, 21, 15000);
        $item = new TestItem('Item', 6, 12, 20, 100, true);
        $itemList = new ItemList();
        for ($i = 0; $i < 12; ++$i) {
            $itemList->insert($item);
        }

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(12, $packedBox->getItems());
    }

    /**
     * From issue #147.
     */
    public function testIssue147A()
    {
        $box = new TestBox('Box', 250, 1360, 260, 0, 250, 1360, 260, 30000);
        $itemList = new ItemList();
        $item = new TestItem('Item', 90, 200, 200, 150, true);

        for ($i = 0; $i < 14; ++$i) {
            $itemList->insert($item);
        }
        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();

        self::assertCount(14, $packedBox->getItems());
    }

    /**
     * From issue #147.
     */
    public function testIssue147B()
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

    /**
     * From issue #161.
     */
    public function testIssue161()
    {
        $box = new TestBox('Box', 240, 150, 180, 0, 240, 150, 180, 10000);
        $item1 = new TestItem('Item 1', 70, 70, 95, 0, false);
        $item2 = new TestItem('Item 2', 95, 75, 95, 0, true);

        $itemList = new ItemList();
        for ($i = 0; $i < 6; ++$i) {
            $itemList->insert($item1);
        }
        for ($i = 0; $i < 3; ++$i) {
            $itemList->insert($item2);
        }
        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();
        self::assertCount(9, $packedBox->getItems());

        $itemList = new ItemList();
        for ($i = 0; $i < 6; ++$i) {
            $itemList->insert($item1);
        }
        for ($i = 0; $i < 2; ++$i) {
            $itemList->insert($item2);
        }
        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();
        self::assertCount(8, $packedBox->getItems());
    }

    /**
     * From issue #164.
     */
    public function testIssue164()
    {
        $box = new TestBox('Box', 820, 820, 830, 0, 820, 820, 830, 10000);

        $itemList = new ItemList();
        $itemList->insert(new TestItem('Item 1', 110, 110, 50, 100, false));
        $itemList->insert(new TestItem('Item 2', 100, 300, 30, 100, false));
        $itemList->insert(new TestItem('Item 3', 100, 150, 50, 100, false));
        $itemList->insert(new TestItem('Item 4', 100, 200, 80, 110, false));
        $itemList->insert(new TestItem('Item 5', 80, 150, 80, 50, false));
        $itemList->insert(new TestItem('Item 6', 80, 150, 80, 50, false));
        $itemList->insert(new TestItem('Item 7', 80, 150, 80, 50, false));
        $itemList->insert(new TestItem('Item 8', 270, 70, 60, 350, false));
        $itemList->insert(new TestItem('Item 9', 150, 150, 80, 180, false));
        $itemList->insert(new TestItem('Item 10', 80, 150, 80, 50, false));

        $packer = new VolumePacker($box, $itemList);
        $packedBox = $packer->pack();
        self::assertCount(10, $packedBox->getItems());
    }

    /**
     * From issue #174.
     */
    public function testIssue174()
    {
        $box = new TestBox('Box', 0, 0, 0, 10, 5000, 5000, 5000, 10000);
        $items = new ItemList();

        $items->insert(new TestItem('Item 0', 1000, 1650, 850, 500, false));
        $items->insert(new TestItem('Item 1', 960, 1640, 800, 500, false));
        $items->insert(new TestItem('Item 2', 950, 1650, 800, 500, false));
        $items->insert(new TestItem('Item 3', 1000, 2050, 800, 500, false));
        $items->insert(new TestItem('Item 4', 1000, 2100, 850, 500, false));
        $items->insert(new TestItem('Item 5', 950, 2050, 800, 500, false));
        $items->insert(new TestItem('Item 6', 940, 970, 800, 500, false));

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(7, $packedBox->getItems());
    }

    /**
     * From issue #172.
     */
    public function testIssue172B()
    {
        $box = new TestBox('Box', 18, 18, 24, 0, 18, 18, 24, 10000);

        $items = new ItemList();
        $item = new TestItem('Larger', 10, 5, 8, 0, false);
        for ($i = 0; $i < 10; ++$i) {
            $items->insert($item);
        }

        for ($i = 0; $i < 5; ++$i) {
            $item = new TestItem('Smaller', 5, 5, 3, 0, false);
            $items->insert($item);
        }

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(15, $packedBox->getItems());
    }

    /**
     * From issue #186.
     */
    public function testPassedInItemListKeepsItems()
    {
        $box = new TestBox('Box', 18, 18, 24, 0, 18, 18, 24, 10000);

        $items = new ItemList();
        $item = new TestItem('Item', 10, 5, 8, 0, false);
        for ($i = 0; $i < 10; ++$i) {
            $items->insert($item);
        }

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(10, $items);
    }

    /**
     * From issue #175.
     */
    public function testIssue175()
    {
        $this->markTestSkipped(); //mark skipped until fixed
        $box = new TestBox('Box', 40, 40, 40, 0, 40, 40, 40, 1000);
        $items = new ItemList();
        $item = new TestItem('Item', 35, 35, 5, 20, false);
        for ($i = 0; $i < 10; ++$i) {
            $items->insert($item);
        }

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack();

        self::assertCount(10, $packedBox->getItems());
    }
}
