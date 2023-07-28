<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\TestCase;

use const PHP_INT_MAX;

class OrientatedItemFactoryTest extends TestCase
{
    public function testAllRotations(): void
    {
        $box = new TestBox('Box', PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX, 0, PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX);
        $factory = new OrientatedItemFactory($box);

        $item = new TestItem('Test', 1, 2, 3, 4, Rotation::BestFit);
        $orientations = $factory->getPossibleOrientations($item, null, $box->getInnerWidth(), $box->getInnerLength(), $box->getInnerDepth(), 0, 0, 0, new PackedItemList());

        self::assertCount(6, $orientations);

        self::assertEquals(1, $orientations[0]->width);
        self::assertEquals(2, $orientations[0]->length);
        self::assertEquals(3, $orientations[0]->depth);

        self::assertEquals(2, $orientations[1]->width);
        self::assertEquals(1, $orientations[1]->length);
        self::assertEquals(3, $orientations[1]->depth);

        self::assertEquals(1, $orientations[2]->width);
        self::assertEquals(3, $orientations[2]->length);
        self::assertEquals(2, $orientations[2]->depth);

        self::assertEquals(2, $orientations[3]->width);
        self::assertEquals(3, $orientations[3]->length);
        self::assertEquals(1, $orientations[3]->depth);

        self::assertEquals(3, $orientations[4]->width);
        self::assertEquals(1, $orientations[4]->length);
        self::assertEquals(2, $orientations[4]->depth);

        self::assertEquals(3, $orientations[5]->width);
        self::assertEquals(2, $orientations[5]->length);
        self::assertEquals(1, $orientations[5]->depth);
    }

    public function testKeepFlat(): void
    {
        $box = new TestBox('Box', PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX, 0, PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX);
        $factory = new OrientatedItemFactory($box);

        $item = new TestItem('Test', 1, 2, 3, 4, Rotation::KeepFlat);
        $orientations = $factory->getPossibleOrientations($item, null, $box->getInnerWidth(), $box->getInnerLength(), $box->getInnerDepth(), 0, 0, 0, new PackedItemList());

        self::assertCount(2, $orientations);

        self::assertEquals(1, $orientations[0]->width);
        self::assertEquals(2, $orientations[0]->length);
        self::assertEquals(3, $orientations[0]->depth);

        self::assertEquals(2, $orientations[1]->width);
        self::assertEquals(1, $orientations[1]->length);
        self::assertEquals(3, $orientations[1]->depth);
    }

    public function testNoRotate(): void
    {
        $box = new TestBox('Box', PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX, 0, PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX);
        $factory = new OrientatedItemFactory($box);

        $item = new TestItem('Test', 1, 2, 3, 4, Rotation::Never);
        $orientations = $factory->getPossibleOrientations($item, null, $box->getInnerWidth(), $box->getInnerLength(), $box->getInnerDepth(), 0, 0, 0, new PackedItemList());

        self::assertCount(1, $orientations);

        self::assertEquals(1, $orientations[0]->width);
        self::assertEquals(2, $orientations[0]->length);
        self::assertEquals(3, $orientations[0]->depth);
    }
}
