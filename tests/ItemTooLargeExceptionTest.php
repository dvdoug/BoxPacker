<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DVDoug\BoxPacker\ItemTooLargeException
 */
class ItemTooLargeExceptionTest extends TestCase
{
    /**
     * Test that the offending item can be retrieved from the object.
     */
    public function testCanGetItem()
    {
        $item = new TestItem('Item 1', 2500, 2500, 20, 2000, true);

        $exception = new ItemTooLargeException('Just testing...', $item);
        self::assertEquals($item, $exception->getItem());
    }
}
