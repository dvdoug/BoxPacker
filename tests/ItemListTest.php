<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Doug
 * Date: 28/07/13
 * Time: 12:58
 * To change this template use File | Settings | File Templates.
 */

  namespace DVDoug\BoxPacker;

  class ItemListTest extends \PHPUnit_Framework_TestCase {

    function testCompare() {

      $box1 = new TestItem('Small', 20, 20, 2, 100);
      $box2 = new TestItem('Large', 200, 200, 20, 1000);
      $box3 = new TestItem('Medium', 100, 100, 10, 500);

      $list = new ItemList;
      $list->insert($box1);
      $list->insert($box2);
      $list->insert($box3);

      $sorted = [];
      while (!$list->isEmpty()) {
        $sorted[] = $list->extract();
      }
      self::assertEquals([$box2,$box3,$box1], $sorted);
    }
  }
