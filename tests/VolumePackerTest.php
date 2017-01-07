<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

class VolumePackerTest extends \PHPUnit_Framework_TestCase
{
    public function testPackBoxThreeItemsFitEasily()
    {

        $box = new TestBox('Le box', 300, 300, 10, 10, 296, 296, 8, 1000);

        $items = new ItemList;
        $items->insert(new TestItem('Item 1', 250, 250, 2, 200, false));
        $items->insert(new TestItem('Item 2', 250, 250, 2, 200, false));
        $items->insert(new TestItem('Item 3', 250, 250, 2, 200, false));

        $packer = new VolumePacker($box, $items);
        $packedBox = $packer->pack();

        self::assertEquals(3, $packedBox->getItems()->count());
    }

    public function testPackBoxThreeItemsFitExactly()
    {

        $box = new TestBox('Le box', 300, 300, 10, 10, 296, 296, 8, 1000);

        $items = new ItemList;
        $items->insert(new TestItem('Item 1', 296, 296, 2, 200, false));
        $items->insert(new TestItem('Item 2', 296, 296, 2, 500, false));
        $items->insert(new TestItem('Item 3', 296, 296, 4, 290, false));

        $packer = new VolumePacker($box, $items);
        $packedBox = $packer->pack();

        self::assertEquals(3, $packedBox->getItems()->count());
    }

    public function testPackBoxThreeItemsFitExactlyNoRotation()
    {

        $box = new TestBox('Le box', 300, 300, 10, 10, 296, 296, 8, 1000);

        $items = new ItemList;
        $items->insert(new TestItem('Item 1', 296, 148, 2, 200, false));
        $items->insert(new TestItem('Item 2', 296, 148, 2, 500, false));

        $packer = new VolumePacker($box, $items);
        $packedBox = $packer->pack();

        self::assertEquals(2, $packedBox->getItems()->count());
    }

    public function testPackBoxThreeItemsFitSizeButOverweight()
    {

        $box = new TestBox('Le box', 300, 300, 10, 10, 296, 296, 8, 1000);

        $items = new ItemList;
        $items->insert(new TestItem('Item 1', 250, 250, 2, 400, false));
        $items->insert(new TestItem('Item 2', 250, 250, 2, 500, false));
        $items->insert(new TestItem('Item 3', 250, 250, 2, 200, false));

        $packer = new VolumePacker($box, $items);
        $packedBox = $packer->pack();

        self::assertEquals(2, $packedBox->getItems()->count());
    }

    public function testPackBoxThreeItemsFitWeightBut2Oversize()
    {

        $box = new TestBox('Le box', 300, 300, 10, 10, 296, 296, 8, 1000);

        $items = new ItemList;
        $items->insert(new TestItem('Item 1', 297, 296, 2, 200, false));
        $items->insert(new TestItem('Item 2', 297, 296, 2, 500, false));
        $items->insert(new TestItem('Item 3', 296, 296, 4, 290, false));

        $packer = new VolumePacker($box, $items);
        $packedBox = $packer->pack();

        self::assertEquals(1, $packedBox->getItems()->count());
    }

    public function testPackTwoItemsFitExactlySideBySide()
    {

        $box = new TestBox('Le box', 300, 400, 10, 10, 296, 496, 8, 1000);

        $items = new ItemList;
        $items->insert(new TestItem('Item 1', 296, 248, 8, 200, false));
        $items->insert(new TestItem('Item 2', 248, 296, 8, 200, false));

        $packer = new VolumePacker($box, $items);
        $packedBox = $packer->pack();

        self::assertEquals(2, $packedBox->getItems()->count());
    }

    public function testPackThreeItemsBottom2FitSideBySideOneExactlyOnTop()
    {

        $box = new TestBox('Le box', 300, 300, 10, 10, 296, 296, 8, 1000);

        $items = new ItemList;
        $items->insert(new TestItem('Item 1', 248, 148, 4, 200, false));
        $items->insert(new TestItem('Item 2', 148, 248, 4, 200, false));
        $items->insert(new TestItem('Item 3', 296, 296, 4, 200, false));

        $packer = new VolumePacker($box, $items);
        $packedBox = $packer->pack();

        self::assertEquals(3, $packedBox->getItems()->count());
    }

    public function testPackThreeItemsBottom2FitSideBySideWithSpareSpaceOneOverhangSlightlyOnTop()
    {

        $box = new TestBox('Le box', 250, 250, 10, 10, 248, 248, 8, 1000);

        $items = new ItemList;
        $items->insert(new TestItem('Item 1', 200, 200, 4, 200, false));
        $items->insert(new TestItem('Item 2', 110, 110, 4, 200, false));
        $items->insert(new TestItem('Item 3', 110, 110, 4, 200, false));

        $packer = new VolumePacker($box, $items);
        $packedBox = $packer->pack();

        self::assertEquals(3, $packedBox->getItems()->count());
    }

    public function testPackSingleItemFitsBetterRotated()
    {

        $box = new TestBox('Le box', 400, 300, 10, 10, 396, 296, 8, 1000);

        $items = new ItemList;
        $items->insert(new TestItem('Item 1', 250, 290, 2, 200, false));

        $packer = new VolumePacker($box, $items);
        $packedBox = $packer->pack();

        self::assertEquals(1, $packedBox->getItems()->count());
    }

    public function testIssue20()
    {
        if (VolumePacker::ALWAYS_SHIP_FLAT === false) {

            $packer = new Packer();
            $packer->addBox(new TestBox('Le grande box', 100, 100, 300, 1, 100,100, 300, 1500));
            $packer->addItem(new TestItem('Item 1', 150, 50, 50, 20, false));
            $packedBoxes = $packer->pack();

            self::assertEquals(1, $packedBoxes->count());
        } else {
            $this->markTestSkipped('3D rotation not enabled');
        }
    }
}
