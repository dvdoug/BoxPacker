<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function count;

/**
 * Enforces the constraint that linked item groups must not be split across boxes.
 * If a packed box contains only some members of a linked group, those members are
 * removed and the box is repacked without them so the freed space can be used by
 * other eligible items.
 *
 * The enforcement loop is iterative and accumulates excluded group IDs so that
 * items drawn in from $items on one pass cannot reintroduce a group that
 * was already excluded on an earlier pass. The loop terminates because the set of
 * excluded groups can only grow, and the eligible item list can only shrink.
 */
class LinkedItemGroupEnforcer
{
    private LoggerInterface $logger;

    private bool $beStrictAboutItemOrdering = false;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function beStrictAboutItemOrdering(bool $beStrict): void
    {
        $this->beStrictAboutItemOrdering = $beStrict;
    }

    public function enforceConstraint(PackedBox $candidate, ItemList $items): PackedBox
    {
        if (!$items->hasLinkedItems()) {
            return $candidate;
        }

        $excludedGroups = [];
        $current = $candidate;

        // while there are linked groups that are incomplete, repack without that linked group
        // so that we fill up the remaining space, while eliminating linked group items that do not fully fit in it
        // when there are no incomplete linked items remaining we can return the packed box.
        while (true) {
            $incompleteLinkedGroups = $this->findIncompleteLinkedGroups($current, $items, $excludedGroups);

            if (count($incompleteLinkedGroups) === 0) {
                return $current;
            }

            $excludedGroups += $incompleteLinkedGroups;

            $eligibleItems = $this->buildEligibleItems($items, $excludedGroups);

            $current = $this->repackBox($candidate, $eligibleItems);
        }
    }

    /**
     * Returns the set of linked group IDs that are not all included in $candidate
     * and are not yet in $alreadyExcluded.
     *
     * @param array<string, true> $alreadyExcluded
     *
     * @return array<string, true>
     */
    private function findIncompleteLinkedGroups(PackedBox $candidate, ItemList $items, array $alreadyExcluded): array
    {
        $linkedGroupCounts = $items->getLinkedGroupCounts();
        $incompleteLinkedGroups = [];

        foreach ($candidate->items->getLinkedGroupCounts() as $groupId => $packedCount) {
            if (!isset($alreadyExcluded[$groupId]) && $packedCount < ($linkedGroupCounts[$groupId] ?? 0)) {
                $incompleteLinkedGroups[$groupId] = true;
            }
        }

        return $incompleteLinkedGroups;
    }

    /**
     * Builds a list of items eligible for repacking: $remainingItems with all members of
     * $excludedGroups removed.
     *
     * @param array<string, true> $excludedGroups
     */
    private function buildEligibleItems(ItemList $items, array $excludedGroups): ItemList
    {
        $eligible = new ItemList();
        foreach ($items as $item) {
            if ($item instanceof LinkedItem && isset($excludedGroups[$item->getLinkedItemGroup()])) {
                continue;
            }
            $eligible->insert($item);
        }

        return $eligible;
    }

    /**
     * Repacks $candidate->box using only $eligibleItems.
     */
    private function repackBox(PackedBox $candidate, ItemList $eligibleItems): PackedBox
    {
        if ($eligibleItems->count() === 0) {
            return new PackedBox($candidate->box, new PackedItemList());
        }

        $volumePacker = new VolumePacker($candidate->box, $eligibleItems);
        $volumePacker->setLogger($this->logger);
        $volumePacker->beStrictAboutItemOrdering($this->beStrictAboutItemOrdering);

        return $volumePacker->pack();
    }
}
