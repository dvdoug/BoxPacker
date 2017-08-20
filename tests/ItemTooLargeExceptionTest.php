<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
declare(strict_types=1);
namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\TestCase;

class ItemTooLargeExceptionTest extends TestCase
{

    function testCanGetItem()
    {

        $item = new TestItem('Item 1', 2500, 2500, 20, 2000, true);

        $exception = new ItemTooLargeException('Just testing...', $item);
        self::assertEquals($item, $exception->getItem());
    }

}
