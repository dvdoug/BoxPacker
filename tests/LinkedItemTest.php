<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Exception\NoBoxesAvailableException;
use DVDoug\BoxPacker\Test\LinkedTestItem;
use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;
use function array_filter;
use function spl_object_id;

class LinkedItemTest extends TestCase
{
    /**
     * Two items in the same group that both fit comfortably: both end up in the same box.
     */
    public function testLinkedItemsPackedInSameBox(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Large box', 100, 100, 100, 0, 100, 100, 100, 10000));

        $item1 = new LinkedTestItem('Item 1', 10, 10, 10, 100, Rotation::BestFit, 'group-A');
        $item2 = new LinkedTestItem('Item 2', 10, 10, 10, 100, Rotation::BestFit, 'group-A');
        $packer->addItem($item1);
        $packer->addItem($item2);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(1, $packedBoxes);
        self::assertCount(2, $packedBoxes[0]->items);
    }

    /**
     * Two linked items that would each individually fit into the small box, but not together.
     * They must be moved to the large box.
     */
    public function testLinkedItemsMovedToLargerBoxWhenRequiredToFitTogether(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Small box', 60, 10, 10, 0, 60, 10, 10, 10000));
        $packer->addBox(new TestBox('Large box', 100, 10, 10, 0, 100, 10, 10, 10000));

        // Each item is 50 wide — fits alone in small box (60), but not both together (needs 100)
        $item1 = new LinkedTestItem('Item 1', 50, 10, 10, 100, Rotation::KeepFlat, 'group-A');
        $item2 = new LinkedTestItem('Item 2', 50, 10, 10, 100, Rotation::KeepFlat, 'group-A');
        $packer->addItem($item1);
        $packer->addItem($item2);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(1, $packedBoxes);
        self::assertEquals('Large box', $packedBoxes[0]->box->getReference());
        self::assertCount(2, $packedBoxes[0]->items);
    }

    /**
     * Three linked items: packer puts them all in one box rather than splitting them.
     */
    public function testThreeLinkedItemsPackedTogether(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Small box', 10, 10, 10, 0, 10, 10, 10, 10000));
        $packer->addBox(new TestBox('Large box', 30, 10, 10, 0, 30, 10, 10, 10000));

        $item1 = new LinkedTestItem('Item 1', 10, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $item2 = new LinkedTestItem('Item 2', 10, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $item3 = new LinkedTestItem('Item 3', 10, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $packer->addItem($item1);
        $packer->addItem($item2);
        $packer->addItem($item3);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(1, $packedBoxes);
        self::assertCount(3, $packedBoxes[0]->items);
    }

    /**
     * When addItem is called with qty > 1, all instances are part of the same linked group.
     */
    public function testQuantityLinkedItemsAreAllLinked(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Small box', 10, 10, 10, 0, 10, 10, 10, 10000));
        $packer->addBox(new TestBox('Large box', 30, 10, 10, 0, 30, 10, 10, 10000));

        $item = new LinkedTestItem('Item', 10, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $packer->addItem($item, 3); // same object, qty 3

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        // All 3 instances share group-A, so they must all be in one box
        self::assertCount(1, $packedBoxes);
        self::assertCount(3, $packedBoxes[0]->items);
    }

    /**
     * Linked items from different groups can be split across boxes.
     */
    public function testDifferentGroupsCanBeSplitAcrossBoxes(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Small box', 50, 10, 10, 0, 50, 10, 10, 10000));

        // group-A: 2 items × 30mm wide = 60mm → won't both fit in 50mm
        $item1 = new LinkedTestItem('Item 1', 30, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $item2 = new LinkedTestItem('Item 2', 20, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        // group-B: 2 items × 20mm wide = 40mm → both fit in 50mm
        $item3 = new LinkedTestItem('Item 3', 20, 10, 10, 10, Rotation::KeepFlat, 'group-B');
        $item4 = new LinkedTestItem('Item 4', 20, 10, 10, 10, Rotation::KeepFlat, 'group-B');

        $packer->addBox(new TestBox('Large box', 60, 10, 10, 0, 60, 10, 10, 10000));
        $packer->addItem($item1);
        $packer->addItem($item2);
        $packer->addItem($item3);
        $packer->addItem($item4);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        // All items fit, two groups must each be intact
        $groupABoxes = array_filter($packedBoxes, static function (PackedBox $box): bool {
            foreach ($box->items as $packedItem) {
                if ($packedItem->item instanceof LinkedTestItem && $packedItem->item->getLinkedItemGroup() === 'group-A') {
                    return true;
                }
            }

            return false;
        });
        $groupBBoxes = array_filter($packedBoxes, static function (PackedBox $box): bool {
            foreach ($box->items as $packedItem) {
                if ($packedItem->item instanceof LinkedTestItem && $packedItem->item->getLinkedItemGroup() === 'group-B') {
                    return true;
                }
            }

            return false;
        });

        // Each group should only appear in exactly one box
        self::assertCount(1, $groupABoxes);
        self::assertCount(1, $groupBBoxes);

        foreach ($groupABoxes as $box) {
            $count = 0;
            foreach ($box->items as $packedItem) {
                if ($packedItem->item instanceof LinkedTestItem && $packedItem->item->getLinkedItemGroup() === 'group-A') {
                    ++$count;
                }
            }
            self::assertSame(2, $count, 'Both group-A items must be in the same box');
        }
        foreach ($groupBBoxes as $box) {
            $count = 0;
            foreach ($box->items as $packedItem) {
                if ($packedItem->item instanceof LinkedTestItem && $packedItem->item->getLinkedItemGroup() === 'group-B') {
                    ++$count;
                }
            }
            self::assertSame(2, $count, 'Both group-B items must be in the same box');
        }
    }

    /**
     * Empty group IDs are not allowed.
     */
    public function testEmptyGroupIdThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 100, 100, 100, 0, 100, 100, 100, 10000));
        $packer->addItem(new LinkedTestItem('Item', 10, 10, 10, 10, Rotation::BestFit, ''));
    }

    /**
     * When throwOnUnpackableItem(false), unpackable linked groups are skipped but other items still pack.
     */
    public function testUnpackableLinkedGroupSkippedWhenNotThrowing(): void
    {
        $packer = new Packer();
        $packer->throwOnUnpackableItem(false);
        $packer->addBox(new TestBox('Tiny box', 5, 5, 5, 0, 5, 5, 5, 10000));

        // This group cannot fit together (each item is 5×5×5, together they need 10 wide)
        $item1 = new LinkedTestItem('Linked 1', 5, 5, 5, 10, Rotation::KeepFlat, 'group-A');
        $item2 = new LinkedTestItem('Linked 2', 5, 5, 5, 10, Rotation::KeepFlat, 'group-A');
        // This standalone item fits fine on its own
        $standalone = new TestItem('Standalone', 5, 5, 5, 10, Rotation::KeepFlat);

        $packer->addItem($item1);
        $packer->addItem($item2);
        $packer->addItem($standalone);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        // Standalone item should still be packed
        $allPackedItems = [];
        foreach ($packedBoxes as $packedBox) {
            foreach ($packedBox->items as $packedItem) {
                $allPackedItems[] = $packedItem->item;
            }
        }
        self::assertContains($standalone, $allPackedItems);
        // Linked items should NOT be packed (group cannot fit together)
        self::assertNotContains($item1, $allPackedItems);
        self::assertNotContains($item2, $allPackedItems);
    }

    /**
     * Unpackable linked group throws NoBoxesAvailableException when throwOnUnpackableItem(true).
     */
    public function testUnpackableLinkedGroupThrowsWhenConfigured(): void
    {
        $this->expectException(NoBoxesAvailableException::class);

        $packer = new Packer();
        $packer->addBox(new TestBox('Tiny box', 5, 5, 5, 0, 5, 5, 5, 10000));

        // Two items in a group, each 5×5×5; they cannot fit together in the 5×5×5 box
        $packer->addItem(new LinkedTestItem('Linked 1', 5, 5, 5, 10, Rotation::KeepFlat, 'group-A'));
        $packer->addItem(new LinkedTestItem('Linked 2', 5, 5, 5, 10, Rotation::KeepFlat, 'group-A'));

        $packer->pack();
    }

    /**
     * packAllPermutations() must never return a permutation that splits a linked group.
     */
    public function testPermutationsPreserveLinkedGroups(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Small box', 50, 10, 10, 0, 50, 10, 10, 10000));
        $packer->addBox(new TestBox('Large box', 100, 10, 10, 0, 100, 10, 10, 10000));

        // Group items that need at least 100mm together
        $item1 = new LinkedTestItem('Item 1', 60, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $item2 = new LinkedTestItem('Item 2', 40, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $packer->addItem($item1);
        $packer->addItem($item2);

        $permutations = $packer->packAllPermutations();

        self::assertNotEmpty($permutations);
        foreach ($permutations as $permutation) {
            $groupBoxes = [];
            foreach ($permutation as $packedBox) {
                foreach ($packedBox->items as $packedItem) {
                    if ($packedItem->item instanceof LinkedTestItem) {
                        $groupId = $packedItem->item->getLinkedItemGroup();
                        // Assign the box index to this group; fail if two different boxes claim the same group
                        $boxRef = spl_object_id($packedBox);
                        if (isset($groupBoxes[$groupId]) && $groupBoxes[$groupId] !== $boxRef) {
                            self::fail("Permutation splits linked group '{$groupId}' across multiple boxes");
                        }
                        $groupBoxes[$groupId] = $boxRef;
                    }
                }
            }
        }
    }

    /**
     * Weight redistribution must not split a linked group between boxes.
     */
    public function testWeightRedistributionDoesNotSplitLinkedGroups(): void
    {
        $packer = new Packer();
        // Two identical boxes
        $packer->addBox(new TestBox('Box', 100, 10, 10, 0, 100, 10, 10, 10000));

        // A linked group of 2 heavy items
        $linkedHeavy1 = new LinkedTestItem('Heavy 1', 20, 10, 10, 400, Rotation::KeepFlat, 'heavy-group');
        $linkedHeavy2 = new LinkedTestItem('Heavy 2', 20, 10, 10, 400, Rotation::KeepFlat, 'heavy-group');
        // Two light standalone items
        $light1 = new TestItem('Light 1', 20, 10, 10, 50, Rotation::KeepFlat);
        $light2 = new TestItem('Light 2', 20, 10, 10, 50, Rotation::KeepFlat);

        $packer->addItem($linkedHeavy1);
        $packer->addItem($linkedHeavy2);
        $packer->addItem($light1);
        $packer->addItem($light2);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        // Find which box contains the heavy-group members
        $heavy1Box = null;
        $heavy2Box = null;
        foreach ($packedBoxes as $packedBox) {
            foreach ($packedBox->items as $packedItem) {
                if ($packedItem->item === $linkedHeavy1) {
                    $heavy1Box = spl_object_id($packedBox);
                }
                if ($packedItem->item === $linkedHeavy2) {
                    $heavy2Box = spl_object_id($packedBox);
                }
            }
        }

        self::assertNotNull($heavy1Box, 'heavy-group item 1 must be packed');
        self::assertNotNull($heavy2Box, 'heavy-group item 2 must be packed');
        self::assertSame($heavy1Box, $heavy2Box, 'Weight redistribution must not split the heavy-group');
    }

    /**
     * Linked items from different groups interleaved with non-linked items pack correctly.
     */
    public function testMixedLinkedAndUnlinkedItems(): void
    {
        $packer = new Packer();
        $packer->addBox(new TestBox('Box', 100, 10, 10, 0, 100, 10, 10, 10000));

        $linked1 = new LinkedTestItem('Linked A1', 20, 10, 10, 100, Rotation::KeepFlat, 'group-A');
        $linked2 = new LinkedTestItem('Linked A2', 20, 10, 10, 100, Rotation::KeepFlat, 'group-A');
        $normal = new TestItem('Normal', 20, 10, 10, 50, Rotation::KeepFlat);

        $packer->addItem($linked1);
        $packer->addItem($normal);
        $packer->addItem($linked2);

        /** @var PackedBox[] $packedBoxes */
        $packedBoxes = iterator_to_array($packer->pack(), false);

        // Verify both linked items end up in the same box
        $linked1Box = null;
        $linked2Box = null;
        foreach ($packedBoxes as $packedBox) {
            foreach ($packedBox->items as $packedItem) {
                if ($packedItem->item === $linked1) {
                    $linked1Box = spl_object_id($packedBox);
                }
                if ($packedItem->item === $linked2) {
                    $linked2Box = spl_object_id($packedBox);
                }
            }
        }
        self::assertSame($linked1Box, $linked2Box, 'Linked group-A items must be in the same box');
    }
}
