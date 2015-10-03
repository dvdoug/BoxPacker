<?php
  /**
   * Box packing (3D bin packing, knapsack problem)
   * @package BoxPacker
   * @author Doug Wright
   */

  namespace DVDoug\BoxPacker;

  class BoxListTest extends \PHPUnit_Framework_TestCase {

    function testCompare() {

      $box1 = new TestBox('Small', 21, 21, 3, 1, 20, 20, 2, 100);
      $box2 = new TestBox('Large', 201, 201, 21, 1, 200, 200, 20, 1000);
      $box3 = new TestBox('Medium', 101, 101, 11, 5, 100, 100, 10, 500);

      $list = new BoxList;
      $list->insert($box1);
      $list->insert($box2);
      $list->insert($box3);

      $sorted = [];
      while (!$list->isEmpty()) {
        $sorted[] = $list->extract();
      }
      self::assertEquals(array($box1,$box3,$box2), $sorted);
    }

    function testCompareWithBigDifference() {
      // If the difference in volume is greater than 2^31 -1, SplHeap's compare() overflows
      // the integer and can't cope. Check we're handling big differences okay.
      $box1 = new TestBox('Small', 21, 21, 3, 1, 20, 20, 2, 100);
      $box2 = new TestBox('Large', 1301, 1301, 1301, 1, 1300, 1300, 1300, 1000);
      $box3 = new TestBox('Medium', 101, 101, 11, 5, 100, 100, 10, 500);

      $list = new BoxList;
      $list->insert($box1);
      $list->insert($box2);
      $list->insert($box3);

      $sorted = [];
      while (!$list->isEmpty()) {
        $sorted[] = $list->extract();
      }
      self::assertEquals(array($box1,$box3,$box2), $sorted);
    }

    function testCompareWithBigDifferenceNotAffectedByInsertOrder() {
      // If the difference in volume is greater than 2^31 -1, SplHeap's compare() overflows
      // the integer and can't cope. Check we're handling big differences okay and that it
      // works with different sort orders
      $box1 = new TestBox('Small', 21, 21, 3, 1, 20, 20, 2, 100);
      $box2 = new TestBox('Large', 1301, 1301, 1301, 1, 1300, 1300, 1300, 1000);
      $box3 = new TestBox('Medium', 101, 101, 11, 5, 100, 100, 10, 500);

      $list = new BoxList;
      $list->insert($box3);
      $list->insert($box2);
      $list->insert($box1);

      $sorted = [];
      while (!$list->isEmpty()) {
        $sorted[] = $list->extract();
      }
      self::assertEquals(array($box1,$box3,$box2), $sorted);

      $list = new BoxList;
      $list->insert($box2);
      $list->insert($box1);
      $list->insert($box3);

      $sorted = [];
      while (!$list->isEmpty()) {
        $sorted[] = $list->extract();
      }
      self::assertEquals(array($box1,$box3,$box2), $sorted);
    }

    function testCompareWithBigAndSmallDifference() {
      // If the difference in volume is greater than 2^31 -1, SplHeap's compare() overflows
      // the integer and can't cope. Check we're handling big differences okay when there's
      // also an item that's almost the same size as the one that's 2^31 bigger than others
      $box1 = new TestBox('Small', 21, 21, 3, 1, 20, 20, 2, 100);
      $box2 = new TestBox('Large', 1301, 1301, 1301, 1, 1300, 1300, 1300, 1000);
      $box3 = new TestBox('Larger', 1302, 1302, 1302, 1, 1301, 1301, 1301, 1000);
      $box4 = new TestBox('Medium', 101, 101, 11, 5, 100, 100, 10, 500);

      $list = new BoxList;
      $list->insert($box1);
      $list->insert($box2);
      $list->insert($box3);
      $list->insert($box4);

      $sorted = [];
      while (!$list->isEmpty()) {
        $sorted[] = $list->extract();
      }
      self::assertEquals(array($box1,$box4,$box2,$box3), $sorted);
    }
  }
