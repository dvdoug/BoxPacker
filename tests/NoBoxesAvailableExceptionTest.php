<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Exception\NoBoxesAvailableException;
use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\TestCase;

class NoBoxesAvailableExceptionTest extends TestCase
{
    /**
     * Test that the offending item can be retrieved from the object.
     */
    public function testCanGetItem(): void
    {
        $item1 = new TestItem('Item 1', 2500, 2500, 20, 2000, Rotation::BestFit);
        $item2 = new TestItem('Item 2', 2500, 2500, 20, 2000, Rotation::BestFit);

        $itemList = new ItemList();
        $itemList->insert($item1);
        $itemList->insert($item2);

        $exception = new NoBoxesAvailableException('Just testing...', $itemList);
        self::assertCount(2, $exception->getAffectedItems());
        self::assertEquals($item1, $exception->getAffectedItems()->extract());
        self::assertEquals($item2, $exception->getAffectedItems()->extract());
    }
}
