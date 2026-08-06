<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VoidFinder::class)]
#[CoversClass(VoidSpace::class)]
class VoidFinderTest extends TestCase
{
    public function testEmptyBoxIsSingleFullVoid(): void
    {
        $spaces = (new VoidFinder())->find(100, 80, 60, []);

        self::assertCount(1, $spaces);
        self::assertSame(0, $spaces[0]->x);
        self::assertSame(0, $spaces[0]->y);
        self::assertSame(0, $spaces[0]->z);
        self::assertSame(100, $spaces[0]->width);
        self::assertSame(80, $spaces[0]->length);
        self::assertSame(60, $spaces[0]->depth);
        self::assertSame(100 * 80 * 60, $spaces[0]->volume);
    }

    public function testSingleItemInCornerProducesResidualsOnOpenSides(): void
    {
        $item = new PackedItem(new TestItem('i', 40, 30, 20, 1, Rotation::BestFit), 0, 0, 0, 40, 30, 20);
        $spaces = (new VoidFinder())->find(100, 80, 60, [$item]);

        self::assertNotEmpty($spaces);
        $this->assertVoidsDoNotIntersectItems($spaces, [$item]);
        $this->assertVoidsInsideBox($spaces, 100, 80, 60);

        self::assertTrue($this->containsVoid($spaces, 40, 0, 0, 60, 80, 60), 'space to the right of the item');
        self::assertTrue($this->containsVoid($spaces, 0, 30, 0, 40, 50, 60), 'space behind the item');
        self::assertTrue($this->containsVoid($spaces, 0, 0, 20, 40, 30, 40), 'space above the item');
    }

    public function testSingleItemInCentreProducesInternalAndExteriorVoids(): void
    {
        $item = new PackedItem(new TestItem('i', 40, 30, 20, 1, Rotation::BestFit), 30, 20, 10, 40, 30, 20);
        $spaces = (new VoidFinder())->find(100, 80, 60, [$item]);

        self::assertNotEmpty($spaces);
        $this->assertVoidsDoNotIntersectItems($spaces, [$item]);
        $this->assertVoidsInsideBox($spaces, 100, 80, 60);

        self::assertTrue($this->containsVoid($spaces, 0, 0, 0, 30, 80, 60), 'left of item');
        self::assertTrue($this->containsVoid($spaces, 70, 0, 0, 30, 80, 60), 'right of item');
        self::assertTrue($this->containsVoid($spaces, 30, 0, 0, 40, 20, 60), 'front of item');
        self::assertTrue($this->containsVoid($spaces, 30, 50, 0, 40, 30, 60), 'back of item');
        self::assertTrue($this->containsVoid($spaces, 30, 20, 0, 40, 30, 10), 'below item');
        self::assertTrue($this->containsVoid($spaces, 30, 20, 30, 40, 30, 30), 'above item');
    }

    public function testTwoItemsLeavingInternalPocket(): void
    {
        // Two blocks leave a clear pocket between them in the middle of the box
        // Box 100×50×50; items at left and right, open pocket 20×50×50 in the middle
        $left = new PackedItem(new TestItem('left', 40, 50, 50, 1, Rotation::BestFit), 0, 0, 0, 40, 50, 50);
        $right = new PackedItem(new TestItem('right', 40, 50, 50, 1, Rotation::BestFit), 60, 0, 0, 40, 50, 50);
        $spaces = (new VoidFinder())->find(100, 50, 50, [$left, $right]);

        $this->assertVoidsDoNotIntersectItems($spaces, [$left, $right]);
        $this->assertVoidsInsideBox($spaces, 100, 50, 50);

        self::assertTrue(
            $this->containsVoid($spaces, 40, 0, 0, 20, 50, 50),
            'Expected free space (internal void) between the two packed items'
        );
    }

    public function testFullyPackedBoxHasNoPositiveVoids(): void
    {
        $item = new PackedItem(new TestItem('i', 100, 80, 60, 1, Rotation::BestFit), 0, 0, 0, 100, 80, 60);
        $spaces = (new VoidFinder())->find(100, 80, 60, [$item]);

        self::assertSame([], $spaces);
    }

    /**
     * @param VoidSpace[]  $spaces
     * @param PackedItem[] $items
     */
    private function assertVoidsDoNotIntersectItems(array $spaces, array $items): void
    {
        foreach ($spaces as $space) {
            foreach ($items as $item) {
                $xOverlap = $space->x < $item->x + $item->width && $item->x < $space->x + $space->width;
                $yOverlap = $space->y < $item->y + $item->length && $item->y < $space->y + $space->length;
                $zOverlap = $space->z < $item->z + $item->depth && $item->z < $space->z + $space->depth;
                self::assertFalse(
                    $xOverlap && $yOverlap && $zOverlap,
                    "Void at ({$space->x},{$space->y},{$space->z}) {$space->width}x{$space->length}x{$space->depth} intersects packed item"
                );
            }
        }
    }

    /**
     * @param VoidSpace[] $spaces
     */
    private function assertVoidsInsideBox(array $spaces, int $boxWidth, int $boxLength, int $boxDepth): void
    {
        foreach ($spaces as $space) {
            self::assertGreaterThanOrEqual(0, $space->x);
            self::assertGreaterThanOrEqual(0, $space->y);
            self::assertGreaterThanOrEqual(0, $space->z);
            self::assertLessThanOrEqual($boxWidth, $space->x + $space->width);
            self::assertLessThanOrEqual($boxLength, $space->y + $space->length);
            self::assertLessThanOrEqual($boxDepth, $space->z + $space->depth);
            self::assertGreaterThan(0, $space->width);
            self::assertGreaterThan(0, $space->length);
            self::assertGreaterThan(0, $space->depth);
        }
    }

    /**
     * @param VoidSpace[] $spaces
     */
    private function containsVoid(array $spaces, int $x, int $y, int $z, int $width, int $length, int $depth): bool
    {
        foreach ($spaces as $space) {
            if (
                $space->x === $x
                && $space->y === $y
                && $space->z === $z
                && $space->width === $width
                && $space->length === $length
                && $space->depth === $depth
            ) {
                return true;
            }
        }

        return false;
    }
}
