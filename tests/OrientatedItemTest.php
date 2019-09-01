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
    public function testSerialize(): void
    {
        $item = new OrientatedItem(new TestItem('Test', 1, 2, 3, 4, false), 1, 2, 3);

        $serializedDataKeys = json_decode(json_encode($item), true);
        self::assertArrayHasKey('item', $serializedDataKeys);
        self::assertArrayHasKey('width', $serializedDataKeys);
        self::assertArrayHasKey('length', $serializedDataKeys);
        self::assertArrayHasKey('depth', $serializedDataKeys);

        self::assertSame('1|2|3', (string) $item);
    }
}
