<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
namespace DVDoug\BoxPacker;

use PHPUnit\Framework\TestCase;

/**
 * @covers \DVDoug\BoxPacker\WorkingVolume
 */
class WorkingVolumeTest extends TestCase
{
    public function testDimensions()
    {
        $volume = new WorkingVolume(1, 2, 3, 4);
        self::assertSame(1, $volume->getInnerWidth());
        self::assertSame(1, $volume->getOuterWidth());
        self::assertSame(2, $volume->getInnerLength());
        self::assertSame(2, $volume->getOuterLength());
        self::assertSame(3, $volume->getInnerDepth());
        self::assertSame(3, $volume->getOuterDepth());
        self::assertSame(0, $volume->getEmptyWeight());
        self::assertSame(4, $volume->getMaxWeight());
    }

    public function testSerialize()
    {
        $volume = new WorkingVolume(1, 2, 3, 4);
        $serializedDataKeys = json_decode(json_encode($volume), true);
        self::assertArrayHasKey('reference', $serializedDataKeys);
        self::assertArrayHasKey('width', $serializedDataKeys);
        self::assertArrayHasKey('length', $serializedDataKeys);
        self::assertArrayHasKey('depth', $serializedDataKeys);
        self::assertArrayHasKey('maxWeight', $serializedDataKeys);
    }
}
