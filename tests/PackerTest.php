<?php
  /**
   * Box packing (3D bin packing, knapsack problem)
   * @package BoxPacker
   * @author Doug Wright
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

      self::assertEquals(3, $packedItems->count());
    }

    public function testPackBoxThreeItemsFitExactly() {

      $box = new TestBox('Le box', 300, 300, 10, 10, 296, 296, 8, 1000);

      $items = new ItemList;
      $items->insert(new TestItem('Item 1', 296, 296, 2, 200));
      $items->insert(new TestItem('Item 2', 296, 296, 2, 500));
      $items->insert(new TestItem('Item 3', 296, 296, 4, 290));

      $packer = new Packer();
      $packedItems = $packer->packBox($box, $items);

      self::assertEquals(3, $packedItems->count());
    }

    public function testPackBoxThreeItemsFitExactlyNoRotation() {

      $box = new TestBox('Le box', 300, 300, 10, 10, 296, 296, 8, 1000);

      $items = new ItemList;
      $items->insert(new TestItem('Item 1', 296, 148, 2, 200));
      $items->insert(new TestItem('Item 2', 296, 148, 2, 500));

      $packer = new Packer();
      $packedItems = $packer->packBox($box, $items);

      self::assertEquals(2, $packedItems->count());
    }

    public function testPackBoxThreeItemsFitSizeButOverweight() {

      $box = new TestBox('Le box', 300, 300, 10, 10, 296, 296, 8, 1000);

      $items = new ItemList;
      $items->insert(new TestItem('Item 1', 250, 250, 2, 400));
      $items->insert(new TestItem('Item 2', 250, 250, 2, 500));
      $items->insert(new TestItem('Item 3', 250, 250, 2, 200));

      $packer = new Packer();
      $packedItems = $packer->packBox($box, $items);

      self::assertEquals(2, $packedItems->count());
    }

    public function testPackBoxThreeItemsFitWeightBut2Oversize() {

      $box = new TestBox('Le box', 300, 300, 10, 10, 296, 296, 8, 1000);

      $items = new ItemList;
      $items->insert(new TestItem('Item 1', 297, 296, 2, 200));
      $items->insert(new TestItem('Item 2', 297, 296, 2, 500));
      $items->insert(new TestItem('Item 3', 296, 296, 4, 290));

      $packer = new Packer();
      $packedItems = $packer->packBox($box, $items);

      self::assertEquals(1, $packedItems->count());
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

    self::assertEquals(1, $packedBoxes->count());
    self::assertEquals(3, $packedBoxes->top()->getItems()->count());
    self::assertEquals($box1, $packedBoxes->top()->getBox());
    self::assertEquals(610, $packedBoxes->top()->getWeight());
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

      self::assertEquals(1, $packedBoxes->count());
      self::assertEquals(3, $packedBoxes->top()->getItems()->count());
      self::assertEquals($box2, $packedBoxes->top()->getBox());
      self::assertEquals(6100, $packedBoxes->top()->getWeight());
    }

    public function testPackFiveItemsTwoLargeOneSmallBox() {

      $box1 = new TestBox('Le petite box', 600, 600, 10, 10, 596, 596, 8, 1000);
      $box2 = new TestBox('Le grande box', 3000, 3000, 50, 100, 2960, 2960, 40, 10000);

      $item1 = new TestItem('Item 1', 2500, 2500, 20, 500);
      $item2 = new TestItem('Item 2', 550, 550, 2, 500);
      $item3 = new TestItem('Item 3', 2500, 2500, 20, 500);
      $item4 = new TestItem('Item 4', 2500, 2500, 20, 500);
      $item5 = new TestItem('Item 5', 2500, 2500, 20, 500);

      $packer = new Packer();
      $packer->addBox($box1);
      $packer->addBox($box2);
      $packer->addItem($item1);
      $packer->addItem($item2);
      $packer->addItem($item3);
      $packer->addItem($item4);
      $packer->addItem($item5);
      $packedBoxes = $packer->pack();

      self::assertEquals(3, $packedBoxes->count());

      self::assertEquals(2, $packedBoxes->top()->getItems()->count());
      self::assertEquals($box2, $packedBoxes->top()->getBox());
      self::assertEquals(1100, $packedBoxes->top()->getWeight());

      $packedBoxes->extract();

      self::assertEquals(2, $packedBoxes->top()->getItems()->count());
      self::assertEquals($box2, $packedBoxes->top()->getBox());
      self::assertEquals(1100, $packedBoxes->top()->getWeight());

      $packedBoxes->extract();

      self::assertEquals(1, $packedBoxes->top()->getItems()->count());
      self::assertEquals($box1, $packedBoxes->top()->getBox());
      self::assertEquals(510, $packedBoxes->top()->getWeight());
    }

    public function testPackFiveItemsTwoLargeOneSmallBoxButThreeAfterRepack() {

      $box1 = new TestBox('Le petite box', 600, 600, 10, 10, 596, 596, 8, 1000);
      $box2 = new TestBox('Le grande box', 3000, 3000, 50, 100, 2960, 2960, 40, 10000);

      $item1 = new TestItem('Item 1', 2500, 2500, 20, 2000);
      $item2 = new TestItem('Item 2', 550, 550, 2, 200);
      $item3 = new TestItem('Item 3', 2500, 2500, 20, 2000);
      $item4 = new TestItem('Item 4', 2500, 2500, 20, 2000);
      $item5 = new TestItem('Item 5', 2500, 2500, 20, 2000);

      $packer = new Packer();
      $packer->addBox($box1);
      $packer->addBox($box2);
      $packer->addItem($item1);
      $packer->addItem($item2);
      $packer->addItem($item3);
      $packer->addItem($item4);
      $packer->addItem($item5);
      $packedBoxes = $packer->pack();

      self::assertEquals(3, $packedBoxes->count());

      self::assertEquals(2, $packedBoxes->top()->getItems()->count());
      self::assertEquals($box2, $packedBoxes->top()->getBox());
      self::assertEquals(4100, $packedBoxes->top()->getWeight());

      $packedBoxes->extract();

      self::assertEquals(2, $packedBoxes->top()->getItems()->count());
      self::assertEquals($box2, $packedBoxes->top()->getBox());
      self::assertEquals(2300, $packedBoxes->top()->getWeight());

      $packedBoxes->extract();

      self::assertEquals(1, $packedBoxes->top()->getItems()->count());
      self::assertEquals($box2, $packedBoxes->top()->getBox());
      self::assertEquals(2100, $packedBoxes->top()->getWeight());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testPackThreeItemsOneDoesntFitInAnyBox() {

      $box1 = new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000);
      $box2 = new TestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000);

      $item1 = new TestItem('Item 1', 2500, 2500, 20, 2000);
      $item2 = new TestItem('Item 2', 25000, 2500, 20, 2000);
      $item3 = new TestItem('Item 3', 2500, 2500, 20, 2000);

      $packer = new Packer();
      $packer->addBox($box1);
      $packer->addBox($box2);
      $packer->addItem($item1);
      $packer->addItem($item2);
      $packer->addItem($item3);
      $packedBoxes = $packer->pack();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testPackWithoutBox() {

      $item1 = new TestItem('Item 1', 2500, 2500, 20, 2000);
      $item2 = new TestItem('Item 2', 25000, 2500, 20, 2000);
      $item3 = new TestItem('Item 3', 2500, 2500, 20, 2000);

      $packer = new Packer();
      $packer->addItem($item1);
      $packer->addItem($item2);
      $packer->addItem($item3);
      $packedBoxes = $packer->pack();
    }

    public function testPackTwoItemsFitExactlySideBySide() {

      $box = new TestBox('Le box', 300, 400, 10, 10, 296, 496, 8, 1000);

      $items = new ItemList;
      $items->insert(new TestItem('Item 1', 296, 248, 8, 200));
      $items->insert(new TestItem('Item 2', 248, 296, 8, 200));

      $packer = new Packer();
      $packedItems = $packer->packBox($box, $items);

      self::assertEquals(2, $packedItems->count());
    }

    public function testPackThreeItemsBottom2FitSideBySideOneExactlyOnTop() {

      $box = new TestBox('Le box', 300, 300, 10, 10, 296, 296, 8, 1000);

      $items = new ItemList;
      $items->insert(new TestItem('Item 1', 248, 148, 4, 200));
      $items->insert(new TestItem('Item 2', 148, 248, 4, 200));
      $items->insert(new TestItem('Item 3', 296, 296, 4, 200));

      $packer = new Packer();
      $packedItems = $packer->packBox($box, $items);

      self::assertEquals(3, $packedItems->count());
    }

    public function testPackThreeItemsBottom2FitSideBySideWithSpareSpaceOneOverhangSlightlyOnTop() {

      $box = new TestBox('Le box', 250, 250, 10, 10, 248, 248, 8, 1000);

      $items = new ItemList;
      $items->insert(new TestItem('Item 1', 200, 200, 4, 200));
      $items->insert(new TestItem('Item 2', 110, 110, 4, 200));
      $items->insert(new TestItem('Item 3', 110, 110, 4, 200));

      $packer = new Packer();
      $packedItems = $packer->packBox($box, $items);

      self::assertEquals(3, $packedItems->count());
    }

    public function testPackSingleItemFitsBetterRotated() {

      $box = new TestBox('Le box', 400, 300, 10, 10, 396, 296, 8, 1000);

      $items = new ItemList;
      $items->insert(new TestItem('Item 1', 250, 290, 2, 200));

      $packer = new Packer();
      $packedItems = $packer->packBox($box, $items);

      self::assertEquals(1, $packedItems->count());
    }

    public function testIssue1() {

      $packer = new Packer();
      $packer->addBox(new TestBox('Le petite box', 292, 336, 60, 10, 292, 336, 60, 9000));
      $packer->addBox(new TestBox('Le grande box', 421, 548, 335, 100, 421, 548, 335, 10000));
      $packer->addItem(new TestItem('Item 1', 226, 200, 40, 440));
      $packer->addItem(new TestItem('Item 2', 200, 200, 155, 1660));
      $packedBoxes = $packer->pack();

      self::assertEquals(1, $packedBoxes->count());
    }

    public function testIssue3() {

      $packer = new Packer();
      $packer->addBox(new TestBox('OW Box 1', 51, 33, 33, 0.6, 51, 33, 33, 0.6));
      $packer->addBox(new TestBox('OW Box 2', 50, 40, 40, 0.95, 50, 40, 40, 0.95));
      $packer->addItem(new TestItem('Product', 28, 19, 9, 0), 6);
      $packedBoxes = $packer->pack();

      self::assertEquals(1, $packedBoxes->count());
    }

    public function testIssue6() {

      $packer = new Packer();
      $packer->addBox(new TestBox('Package 22', 675, 360, 210, 2, 670, 355, 204, 1000));
      $packer->addBox(new TestBox('Package 2', 330, 130, 102, 2, 335, 135, 107, 1000));
      $packer->addItem(new TestItem('Item 3', 355.6, 335.28, 127, 1.5));
      $packer->addItem(new TestItem('Item 7', 330.2, 127, 101.6, 1));
      $packer->addItem(new TestItem('Item 7', 330.2, 127, 101.6, 1));
      $packedBoxes = $packer->pack();

      self::assertEquals(2, $packedBoxes->count());

    }

    public function testIssue9() {
      $packer = new Packer();
      $packer->addBox(new TestBox('24x24x24Box', 24, 24, 24, 24, 24, 24, 24, 100));

      $packer->addItem(new TestItem('6x6x6Item', 6, 6, 6, 1), 64);
      $packedBoxes = $packer->pack();

      self::assertEquals(1, $packedBoxes->count());
    }

    public function testIssue11() {
      $packer = new Packer();
      $packer->addBox(new TestBox('4x4x4Box', 4, 4, 4, 4, 4, 4, 4, 100));

      $packer->addItem(new TestItem('BigItem', 2, 2, 4, 1), 2);
      $packer->addItem(new TestItem('SmallItem', 1, 1, 1, 1), 32);
      $packedBoxes = $packer->pack();

      self::assertEquals(1, $packedBoxes->count());
    }

    public function testIssue13() {
      $packer = new Packer();
      $packer->addBox(new TestBox('Le petite box', 12, 12, 12, 10, 10, 10, 10, 1000));

      $packer->addItem(new TestItem('Item 1', 5, 3, 2, 2));
      $packer->addItem(new TestItem('Item 2', 5, 3, 2, 2));
      $packer->addItem(new TestItem('Item 3', 3, 3, 3, 3));
      $packedBoxes = $packer->pack();

      self::assertEquals(1, $packedBoxes->count());
    }

    public function testIssue14() {
      $packer = new Packer();
      $packer->addBox(new TestBox('29x1x23Box', 29, 1, 23, 0, 29, 1, 23, 100));
      $packer->addItem(new TestItem('13x1x10Item', 13, 1, 10, 1));
      $packer->addItem(new TestItem('9x1x6Item', 9, 1, 6, 1));
      $packer->addItem(new TestItem('9x1x6Item', 9, 1, 6, 1));
      $packer->addItem(new TestItem('9x1x6Item', 9, 1, 6, 1));
      $packedBoxes = $packer->pack();

      self::assertEquals(1, $packedBoxes->count());
    }


    /**
     * @dataProvider getSamples
     */
    public function testCanPackRepresentativeLargerSamples($test, $boxes, $items, $expectedBoxes, $expectedWeightVariance) {

      $expectedItemCount = 0;
      $packedItemCount = 0;

      $packer = new Packer();
      foreach($boxes as $box) {
        $packer->addBox($box);
      }
      foreach ($items as $item) {
        $packer->addItem(new TestItem($item['name'], $item['width'], $item['length'], $item['depth'], $item['weight']), $item['qty']);
        $expectedItemCount += $item['qty'];
      }
      $packedBoxes = $packer->pack();

      foreach (clone $packedBoxes as $packedBox) {
        $packedItemCount += $packedBox->getItems()->count();
      }


      self::assertEquals($expectedBoxes, $packedBoxes->count());
      self::assertEquals($expectedItemCount, $packedItemCount);
      self::assertEquals($expectedWeightVariance, (int) $packedBoxes->getWeightVariance());

    }

    public function getSamples() {

      $expected = [];
      $expectedData = fopen(__DIR__ . '/data/expected.csv', 'r');
      while ($data = fgetcsv($expectedData)) {
        $expected[$data[0]] = array('boxes' => $data[1], 'weightVariance' => $data[2]);
      }
      fclose($expectedData);

      $boxes = [];
      $boxData = fopen(__DIR__ . '/data/boxes.csv', 'r');
      while ($data = fgetcsv($boxData)) {
        $boxes[] = new TestBox($data[0], $data[1], $data[2], $data[3], $data[4], $data[5], $data[6], $data[7], $data[8]);
      }
      fclose($boxData);

      $tests = [];
      $itemData = fopen(__DIR__ . '/data/items.csv', 'r');
      while ($data = fgetcsv($itemData)) {
        if (isset($tests[$data[0]])) {
          $tests[$data[0]]['items'][] = array('qty' => $data[1],
                                              'name' => $data[2],
                                              'width' => $data[3],
                                              'length' => $data[4],
                                              'depth' => $data[5],
                                              'weight' => $data[6]);
        }
        else {
          $tests[$data[0]] = array('test'  => $data[0],
                                   'boxes' => $boxes,
                                   'items' => array(array('qty' => $data[1],
                                                          'name' => $data[2],
                                                          'width' => $data[3],
                                                          'length' => $data[4],
                                                          'depth' => $data[5],
                                                          'weight' => $data[6])),
                                   'expected' => $expected[$data[0]]['boxes'],
                                   'weightVariance' => $expected[$data[0]]['weightVariance']);
        }
      }
      fclose($itemData);

      return $tests;
    }

  }
