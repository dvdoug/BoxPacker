<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Doug
 * Date: 28/07/13
 * Time: 12:58
 * To change this template use File | Settings | File Templates.
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
      self::assertEquals([$box1,$box3,$box2], $sorted);
    }
  }
