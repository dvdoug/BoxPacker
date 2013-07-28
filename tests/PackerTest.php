<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Doug
 * Date: 28/07/13
 * Time: 12:58
 * To change this template use File | Settings | File Templates.
 */

  namespace DVDoug\BoxPacker;

  class PackerTest extends \PHPUnit_Framework_TestCase {

    public function testPackBoxThreeItemsFitEasily() {

      $box = new TestBox('Le box', 300, 300, 10, 10, 296, 296, 8, 1000);

      $items = new ItemList;
      $items->insert(new TestItem('Item 1', 250, 250, 2, 200));
      $items->insert(new TestItem('Item 2', 250, 250, 2, 200));
      $items->insert(new TestItem('Item 3', 250, 250, 2, 200));

      $packer = new Packer();
      $packedItems = $packer->packBox($box, $items);

      self::assertEquals(3, sizeof($packedItems));
    }

    public function testPackBoxThreeItemsFitExactly() {

      $box = new TestBox('Le box', 300, 300, 10, 10, 296, 296, 8, 1000);

      $items = new ItemList;
      $items->insert(new TestItem('Item 1', 296, 296, 2, 200));
      $items->insert(new TestItem('Item 2', 296, 296, 2, 500));
      $items->insert(new TestItem('Item 3', 296, 296, 4, 290));

      $packer = new Packer();
      $packedItems = $packer->packBox($box, $items);

      self::assertEquals(3, sizeof($packedItems));
    }

    public function testPackBoxThreeItemsFitSizeButOverweight() {

      $box = new TestBox('Le box', 300, 300, 10, 10, 296, 296, 8, 1000);

      $items = new ItemList;
      $items->insert(new TestItem('Item 1', 250, 250, 2, 400));
      $items->insert(new TestItem('Item 2', 250, 250, 2, 500));
      $items->insert(new TestItem('Item 3', 250, 250, 2, 200));

      $packer = new Packer();
      $packedItems = $packer->packBox($box, $items);

      self::assertEquals(2, sizeof($packedItems));
    }

    public function testPackBoxThreeItemsFitWeightBut2Oversize() {

      $box = new TestBox('Le box', 300, 300, 10, 10, 296, 296, 8, 1000);

      $items = new ItemList;
      $items->insert(new TestItem('Item 1', 297, 296, 2, 200));
      $items->insert(new TestItem('Item 2', 297, 296, 2, 500));
      $items->insert(new TestItem('Item 3', 296, 296, 4, 290));

      $packer = new Packer();
      $packedItems = $packer->packBox($box, $items);

      self::assertEquals(1, sizeof($packedItems));
    }

    public function testPackThreeItemsFitEasilyInSmallerOfTwoBoxes() {

    $box1 = new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000);
    $box2 = new TestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000);

    $item1 = new TestItem('Item 1', 250, 250, 2, 200);
    $item2 = new TestItem('Item 2', 250, 250, 2, 200);
    $item3 = new TestItem('Item 3', 250, 250, 2, 200);

    $packer = new Packer();
    $packer->addBox($box1);
    $packer->addBox($box2);
    $packer->addItem($item1);
    $packer->addItem($item2);
    $packer->addItem($item3);
    $packedBoxes = $packer->pack();

    self::assertEquals(1, sizeof($packedBoxes));
    self::assertEquals(3, $packedBoxes[0]->getItems()->count());
    self::assertEquals($box1, $packedBoxes[0]->getBox());
    self::assertEquals(610, $packedBoxes[0]->getWeight());
  }

    public function testPackThreeItemsFitEasilyInLargerOfTwoBoxes() {

      $box1 = new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000);
      $box2 = new TestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000);

      $item1 = new TestItem('Item 1', 2500, 2500, 20, 2000);
      $item2 = new TestItem('Item 2', 2500, 2500, 20, 2000);
      $item3 = new TestItem('Item 3', 2500, 2500, 20, 2000);

      $packer = new Packer();
      $packer->addBox($box1);
      $packer->addBox($box2);
      $packer->addItem($item1);
      $packer->addItem($item2);
      $packer->addItem($item3);
      $packedBoxes = $packer->pack();

      self::assertEquals(1, sizeof($packedBoxes));
      self::assertEquals(3, $packedBoxes[0]->getItems()->count());
      self::assertEquals($box2, $packedBoxes[0]->getBox());
      self::assertEquals(6100, $packedBoxes[0]->getWeight());
    }

  }
