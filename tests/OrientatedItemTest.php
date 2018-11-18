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

/**
 * @covers \DVDoug\BoxPacker\OrientatedItem
 */
class OrientatedItemTest extends TestCase
{
    /**
     * Sometimes people use a 0 depth.
     */
    public function testZeroDepth(): void
    {
        $orientatedItem = new OrientatedItem(new TestItem('Item', 1, 1, 0, 0, false), 1, 1, 0);
        $tippingPoint = $orientatedItem->getTippingPoint();
        $this->expectNotToPerformAssertions();
    }
}
