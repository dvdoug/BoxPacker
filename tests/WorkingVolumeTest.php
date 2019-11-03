<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use function json_decode;
use function json_encode;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DVDoug\BoxPacker\WorkingVolume
 */
class WorkingVolumeTest extends TestCase
{
    /**
     * @var WorkingVolume
     */
    private $volume;

    public function setUp(): void
    {
        $this->volume = new WorkingVolume(1, 2, 3, 4);
    }

    public function testDimensions(): void
    {
        self::assertSame(1, $this->volume->getInnerWidth());
        self::assertSame(1, $this->volume->getOuterWidth());
        self::assertSame(2, $this->volume->getInnerLength());
        self::assertSame(2, $this->volume->getOuterLength());
        self::assertSame(3, $this->volume->getInnerDepth());
        self::assertSame(3, $this->volume->getOuterDepth());
        self::assertSame(0, $this->volume->getEmptyWeight());
        self::assertSame(4, $this->volume->getMaxWeight());
    }

    public function testSerialize(): void
    {
        $serializedDataKeys = json_decode(json_encode($this->volume), true);
        self::assertArrayHasKey('reference', $serializedDataKeys);
        self::assertArrayHasKey('width', $serializedDataKeys);
        self::assertArrayHasKey('length', $serializedDataKeys);
        self::assertArrayHasKey('depth', $serializedDataKeys);
        self::assertArrayHasKey('maxWeight', $serializedDataKeys);
    }
}
