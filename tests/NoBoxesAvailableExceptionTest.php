<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\TestCase;

class NoBoxesAvailableExceptionTest extends TestCase
{
    /**
     * Test that the offending item can be retrieved from the object.
     */
    public function testCanGetItem(): void
    {
        $item = new TestItem('Item 1', 2500, 2500, 20, 2000, Rotation::BestFit);

        $exception = new NoBoxesAvailableException('Just testing...', $item);
        self::assertEquals($item, $exception->getItem());
    }
}
