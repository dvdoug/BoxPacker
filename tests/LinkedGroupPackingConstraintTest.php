<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\LinkedTestItem;
use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\TestCase;

use function in_array;

class LinkedGroupPackingConstraintTest extends TestCase
{
    /**
     * When $remainingItems contains no LinkedItem instances, hasLinkedItems() returns false
     * and the candidate must be returned unchanged via the early-return path (no loop entered).
     */
    public function testEnforceConstraintNoLinkedItemsReturnsCandidateUnchanged(): void
    {
        $box = new TestBox('Box', 100, 10, 10, 0, 100, 10, 10, 10000);

        $items = new ItemList();
        $items->insert(new TestItem('Item 1', 30, 10, 10, 10, Rotation::KeepFlat));
        $items->insert(new TestItem('Item 2', 30, 10, 10, 10, Rotation::KeepFlat));

        $volumePacker = new VolumePacker($box, $items);
        $candidate = $volumePacker->pack();

        $constraint = new LinkedGroupPackingConstraint();
        $result = $constraint->enforceConstraint($candidate, $items);

        self::assertSame(
            $candidate,
            $result,
            'Candidate must be returned unchanged when $remainingItems has no linked items'
        );
    }

    /**
     * When all members of every linked group are present in the packed box (no partial groups),
     * findNewPartialGroups() returns empty on the first iteration and the candidate must be
     * returned unchanged without any repack.
     */
    public function testEnforceConstraintAllGroupsCompleteReturnsCandidateUnchanged(): void
    {
        $box = new TestBox('Box', 100, 10, 10, 0, 100, 10, 10, 10000);

        $item1 = new LinkedTestItem('Item 1', 30, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $item2 = new LinkedTestItem('Item 2', 30, 10, 10, 10, Rotation::KeepFlat, 'group-A');

        $items = new ItemList();
        $items->insert($item1);
        $items->insert($item2);

        $volumePacker = new VolumePacker($box, $items);
        $candidate = $volumePacker->pack();

        $remainingItems = new ItemList();
        $remainingItems->insert($item1);
        $remainingItems->insert($item2);

        $constraint = new LinkedGroupPackingConstraint();
        $result = $constraint->enforceConstraint($candidate, $remainingItems);

        self::assertSame($candidate, $result, 'Candidate must be returned unchanged when no partial groups exist');
    }

    /**
     * When the candidate contains no packed linked items but $remainingItems does contain
     * linked items, getLinkedGroupCounts() on the packed list returns empty and
     * findNewPartialGroups() finds nothing to exclude — the candidate is returned unchanged.
     */
    public function testEnforceConstraintCandidateHasNoLinkedItemsPacked(): void
    {
        $box = new TestBox('Box', 100, 10, 10, 0, 100, 10, 10, 10000);

        $normal = new TestItem('Normal', 10, 10, 10, 10, Rotation::KeepFlat);
        $linked1 = new LinkedTestItem('Linked 1', 30, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $linked2 = new LinkedTestItem('Linked 2', 30, 10, 10, 10, Rotation::KeepFlat, 'group-A');

        // Candidate contains only the non-linked item.
        $packedItems = new ItemList();
        $packedItems->insert($normal);
        $volumePacker = new VolumePacker($box, $packedItems);
        $candidate = $volumePacker->pack();

        // $remainingItems includes linked items, but the candidate never packed any of them.
        $remainingItems = new ItemList();
        $remainingItems->insert($normal);
        $remainingItems->insert($linked1);
        $remainingItems->insert($linked2);

        $constraint = new LinkedGroupPackingConstraint();
        $result = $constraint->enforceConstraint($candidate, $remainingItems);

        self::assertSame($candidate, $result, 'Candidate must be unchanged when no linked items were packed in it');
    }

    /**
     * When every item in the candidate belongs to a partial linked group and there are no
     * non-linked items, after exclusion the restricted list is empty and the result must be
     * a PackedBox for the same box containing zero items.
     */
    public function testEnforceConstraintAllItemsArePartialGroupProducesEmptyBox(): void
    {
        $box = new TestBox('Box', 60, 10, 10, 0, 60, 10, 10, 10000);

        // Only A1 fits; A1 + A2 = 100mm > 60mm box.
        $groupA1 = new LinkedTestItem('Group A - 1', 50, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $groupA2 = new LinkedTestItem('Group A - 2', 50, 10, 10, 10, Rotation::KeepFlat, 'group-A');

        $packedItems = new ItemList();
        $packedItems->insert($groupA1);
        $volumePacker = new VolumePacker($box, $packedItems);
        $candidate = $volumePacker->pack();

        $remainingItems = new ItemList();
        $remainingItems->insert($groupA1);
        $remainingItems->insert($groupA2);

        $constraint = new LinkedGroupPackingConstraint();
        $result = $constraint->enforceConstraint($candidate, $remainingItems);

        self::assertSame($box, $result->box, 'Box reference must be preserved');
        self::assertCount(0, $result->items, 'Result must be empty when all candidate items belong to a partial group');
    }

    /**
     * When two different linked groups are both partial in the original candidate,
     * both must be identified and excluded in a single buildRestrictedItems pass,
     * leaving only non-linked items in the repacked result.
     */
    public function testEnforceConstraintTwoSimultaneousPartialGroupsBothRemoved(): void
    {
        // Box: 100mm. Candidate: A1(50) + B1(15) + regular(10) = 75mm.
        // A2(50) and B2(15) exist in $remainingItems but were not packed in the candidate.
        // Both group-A and group-B are partial simultaneously, so both must be removed
        // in the same repack pass, leaving only the regular item.
        $box = new TestBox('Box', 100, 10, 10, 0, 100, 10, 10, 10000);

        $groupA1 = new LinkedTestItem('Group A - 1', 50, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $groupA2 = new LinkedTestItem('Group A - 2', 50, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $groupB1 = new LinkedTestItem('Group B - 1', 15, 10, 10, 10, Rotation::KeepFlat, 'group-B');
        $groupB2 = new LinkedTestItem('Group B - 2', 15, 10, 10, 10, Rotation::KeepFlat, 'group-B');
        $regular = new TestItem('Regular', 10, 10, 10, 10, Rotation::KeepFlat);

        $packedItems = new ItemList();
        $packedItems->insert($groupA1);
        $packedItems->insert($groupB1);
        $packedItems->insert($regular);
        $volumePacker = new VolumePacker($box, $packedItems);
        $candidate = $volumePacker->pack();

        $remainingItems = new ItemList();
        $remainingItems->insert($groupA1);
        $remainingItems->insert($groupA2);
        $remainingItems->insert($groupB1);
        $remainingItems->insert($groupB2);
        $remainingItems->insert($regular);

        $constraint = new LinkedGroupPackingConstraint();
        $result = $constraint->enforceConstraint($candidate, $remainingItems);

        $packedItemObjects = [];
        foreach ($result->items as $packedItem) {
            $packedItemObjects[] = $packedItem->item;
        }

        self::assertNotContains($groupA1, $packedItemObjects, 'Partial group-A member must be excluded');
        self::assertNotContains($groupB1, $packedItemObjects, 'Partial group-B member must be excluded');
        self::assertContains($regular, $packedItemObjects, 'Non-linked item must be retained');
    }

    /**
     * Smoke test: enforceConstraint() orchestrates removal and repacking correctly.
     * A partial linked group member must be absent from the result while a non-linked
     * item must remain.
     */
    public function testEnforceConstraintPartialGroupRemovedAndBoxRepacked(): void
    {
        $box = new TestBox('Box', 60, 10, 10, 0, 60, 10, 10, 10000);

        $item1 = new LinkedTestItem('Linked 1', 50, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $item2 = new LinkedTestItem('Linked 2', 50, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $standalone = new TestItem('Standalone', 30, 10, 10, 10, Rotation::KeepFlat);

        $packedItems = new ItemList();
        $packedItems->insert($item1);
        $packedItems->insert($standalone);

        $volumePacker = new VolumePacker($box, $packedItems);
        $candidate = $volumePacker->pack();

        $remainingItems = new ItemList();
        $remainingItems->insert($item1);
        $remainingItems->insert($item2);
        $remainingItems->insert($standalone);

        $constraint = new LinkedGroupPackingConstraint();
        $result = $constraint->enforceConstraint($candidate, $remainingItems);

        $packedItemObjects = [];
        foreach ($result->items as $packedItem) {
            $packedItemObjects[] = $packedItem->item;
        }

        self::assertNotContains($item1, $packedItemObjects, 'Partial linked group member must be removed');
        self::assertContains($standalone, $packedItemObjects, 'Non-linked item must remain packed');
    }

    /**
     * When group-A is partial and group-B is complete (both members in the candidate),
     * enforceConstraint() must produce a repacked box that contains both group-B items.
     */
    public function testEnforceConstraintCompleteGroupPreservedAlongsidePartialGroup(): void
    {
        // Box fits groupA1(60) + groupB1(15) + groupB2(15) = 90 ≤ 100.
        // group-A is partial (A2 not in candidate); group-B is complete.
        $box = new TestBox('Box', 100, 10, 10, 0, 100, 10, 10, 10000);

        $groupA1 = new LinkedTestItem('Group A - 1', 60, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $groupA2 = new LinkedTestItem('Group A - 2', 60, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $groupB1 = new LinkedTestItem('Group B - 1', 15, 10, 10, 10, Rotation::KeepFlat, 'group-B');
        $groupB2 = new LinkedTestItem('Group B - 2', 15, 10, 10, 10, Rotation::KeepFlat, 'group-B');

        $packedItems = new ItemList();
        $packedItems->insert($groupA1);
        $packedItems->insert($groupB1);
        $packedItems->insert($groupB2);

        $volumePacker = new VolumePacker($box, $packedItems);
        $candidate = $volumePacker->pack();

        $remainingItems = new ItemList();
        $remainingItems->insert($groupA1);
        $remainingItems->insert($groupA2);
        $remainingItems->insert($groupB1);
        $remainingItems->insert($groupB2);

        $constraint = new LinkedGroupPackingConstraint();
        $result = $constraint->enforceConstraint($candidate, $remainingItems);

        $packedItemObjects = [];
        foreach ($result->items as $packedItem) {
            $packedItemObjects[] = $packedItem->item;
        }

        self::assertNotContains($groupA1, $packedItemObjects, 'Partial group-A member must be excluded');
        self::assertContains($groupB1, $packedItemObjects, 'Complete group-B member must be in result');
        self::assertContains($groupB2, $packedItemObjects, 'Complete group-B member must be in result');
    }

    /**
     * When 3 distinct linked products each have their own group and A and B cannot
     * all fit in the box (only partial members of those groups pack), but C's two
     * members both fit, the constraint must remove groups A and B and return a box
     * containing only the complete group C.
     *
     * Box: 100mm.
     * Candidate (built from A1+C1+C2): A1(60) + C1(20) + C2(20) = 100mm.
     *   group-A is partial (A2 absent) → removed on first pass.
     * First repack [B1,B2,C1,C2]: B1(60)+C1(20)+C2(20)=100mm; B2 doesn't fit.
     *   group-B is partial → removed on second pass.
     * Second repack [C1,C2]: 40mm, both fit and group-C is complete → returned.
     */
    public function testThreeDistinctGroupsOnlyFittingGroupIsPacked(): void
    {
        $box = new TestBox('Box', 100, 10, 10, 0, 100, 10, 10, 10000);

        $groupA1 = new LinkedTestItem('Group A - 1', 60, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $groupA2 = new LinkedTestItem('Group A - 2', 60, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $groupB1 = new LinkedTestItem('Group B - 1', 60, 10, 10, 10, Rotation::KeepFlat, 'group-B');
        $groupB2 = new LinkedTestItem('Group B - 2', 60, 10, 10, 10, Rotation::KeepFlat, 'group-B');
        $groupC1 = new LinkedTestItem('Group C - 1', 20, 10, 10, 10, Rotation::KeepFlat, 'group-C');
        $groupC2 = new LinkedTestItem('Group C - 2', 20, 10, 10, 10, Rotation::KeepFlat, 'group-C');

        // Candidate: A1(60) + C1(20) + C2(20) = 100mm — group-A partial, group-C complete.
        $packedItems = new ItemList();
        $packedItems->insert($groupA1);
        $packedItems->insert($groupC1);
        $packedItems->insert($groupC2);

        $volumePacker = new VolumePacker($box, $packedItems);
        $candidate = $volumePacker->pack();

        $remainingItems = new ItemList();
        $remainingItems->insert($groupA1);
        $remainingItems->insert($groupA2);
        $remainingItems->insert($groupB1);
        $remainingItems->insert($groupB2);
        $remainingItems->insert($groupC1);
        $remainingItems->insert($groupC2);

        $constraint = new LinkedGroupPackingConstraint();
        $result = $constraint->enforceConstraint($candidate, $remainingItems);

        $packedItemObjects = [];
        foreach ($result->items as $packedItem) {
            $packedItemObjects[] = $packedItem->item;
        }

        self::assertNotContains($groupA1, $packedItemObjects, 'Partial group-A must not be packed');
        self::assertNotContains($groupA2, $packedItemObjects, 'Partial group-A must not be packed');
        self::assertNotContains($groupB1, $packedItemObjects, 'Partial group-B must not be packed');
        self::assertNotContains($groupB2, $packedItemObjects, 'Partial group-B must not be packed');
        self::assertContains($groupC1, $packedItemObjects, 'Complete group-C member must be packed');
        self::assertContains($groupC2, $packedItemObjects, 'Complete group-C member must be packed');
    }

    /**
     * candidate, the recursive enforcement in enforceConstraint() must prevent those
     * items from forming a new partial group in the repacked result.
     *
     * Scenario:
     *   - Box: 40mm wide.
     *   - Original candidate: [A1(30mm, group-A partial), regular(10mm)].
     *   - $remainingItems also has C1(20mm, group-C) and C2(25mm, group-C).
     *   - First repack uses restrictedItems = [regular(10), C1(20), C2(25)].
     *     VolumePacker sorts largest-first: C2(25) + regular(10) = 35 fits; C1(20) → 55 > 40 — no.
     *     Without the recursive call, the result [C2, regular] has group-C partial (C2 packed, C1 not).
     *   - The recursive call detects group-C partial, rebuilds restrictedItems = [regular],
     *     and produces a clean result containing only the regular item.
     */
    public function testEnforceConstraintDoesNotCreateNewPartialGroupFromRemainingItems(): void
    {
        // Box is 40mm wide.
        // A1 = 30mm (group-A, partial — A2 also in remaining but not in candidate).
        // regular = 10mm (fits alongside A1 in the original candidate; small enough to
        //   fit alongside C2 in the first repack, triggering the partial-group scenario).
        // C2 = 25mm, C1 = 20mm (group-C — C2 fits with regular(10) = 35 ≤ 40,
        //   but C2(25) + C1(20) = 45 > 40 so C1 cannot join C2 in the repack).
        $box = new TestBox('Box', 40, 10, 10, 0, 40, 10, 10, 10000);

        $groupA1 = new LinkedTestItem('Group A - 1', 30, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $groupA2 = new LinkedTestItem('Group A - 2', 30, 10, 10, 10, Rotation::KeepFlat, 'group-A');
        $regular = new TestItem('Regular', 10, 10, 10, 10, Rotation::KeepFlat);
        $groupC1 = new LinkedTestItem('Group C - 1', 20, 10, 10, 10, Rotation::KeepFlat, 'group-C');
        $groupC2 = new LinkedTestItem('Group C - 2', 25, 10, 10, 10, Rotation::KeepFlat, 'group-C');

        // Original candidate: A1(30) + regular(10) = 40mm — exactly fills the box.
        $packedItems = new ItemList();
        $packedItems->insert($groupA1);
        $packedItems->insert($regular);

        $volumePacker = new VolumePacker($box, $packedItems);
        $candidate = $volumePacker->pack();

        // $remainingItems also has A2, C1, C2.  When the first repack removes A1/A2
        // (group-A partial), it will try to fit [regular(10), C1(20), C2(25)].
        // VolumePacker picks C2(25) first; C2+regular=35 fits; C1(20) would push it to 55 — no.
        // Result of first repack: [C2, regular] — group-C is now partial (C2 in, C1 out).
        // The recursive enforceConstraint call must catch this and strip group-C members,
        // yielding a final result that contains only the regular item.
        $remainingItems = new ItemList();
        $remainingItems->insert($groupA1);
        $remainingItems->insert($groupA2);
        $remainingItems->insert($regular);
        $remainingItems->insert($groupC1);
        $remainingItems->insert($groupC2);

        $constraint = new LinkedGroupPackingConstraint();
        $result = $constraint->enforceConstraint($candidate, $remainingItems);

        $packedItemObjects = [];
        foreach ($result->items as $packedItem) {
            $packedItemObjects[] = $packedItem->item;
        }

        // group-A members must not be present (partial group)
        self::assertNotContains($groupA1, $packedItemObjects, 'Partial group-A must not be in result');

        // group-C must not be split: either both C1 and C2 are present, or neither is
        $c1Present = in_array($groupC1, $packedItemObjects, true);
        $c2Present = in_array($groupC2, $packedItemObjects, true);
        self::assertSame($c1Present, $c2Present, 'group-C must not be split: both or neither must be present');

        // The regular item must be packed — it is the only valid item once both partial
        // groups (A and C) are stripped, and it was proven to fit in the original candidate.
        self::assertContains($regular, $packedItemObjects, 'Regular item from original candidate must remain packed');
    }
}
