<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use function count;
use DVDoug\BoxPacker\Exception\NoBoxesAvailableException;

/**
 * A version of the packer that swallows internal exceptions.
 */
class InfalliblePacker extends Packer
{
    protected ItemList $unpackedItems;

    public function __construct()
    {
        $this->unpackedItems = new ItemList();
        parent::__construct();
    }

    /**
     * Return the items that couldn't be packed.
     */
    public function getUnpackedItems(): ItemList
    {
        return $this->unpackedItems;
    }

    /**
     * {@inheritdoc}
     */
    public function pack(): PackedBoxList
    {
        $this->sanityPrecheck();
        $originalItemList = clone $this->items;
        while (true) {
            try {
                $this->items = clone $originalItemList;
                foreach($this->unpackedItems as $unpackedItem) {
                    $this->items->remove($unpackedItem);
                }

                return parent::pack();
            } catch (NoBoxesAvailableException $e) {
                foreach ($e->getAffectedItems() as $affectedItem) {
                    $this->unpackedItems->insert($affectedItem);
                }
            }
        }
    }

    private function sanityPrecheck(): void
    {
        $cache = [];

        foreach ($this->items as $item) {
            $cacheKey = $item->getWidth() .
                '|' .
                $item->getLength() .
                '|' .
                $item->getDepth() .
                '|' .
                $item->getAllowedRotation()->name;

            foreach ($this->boxes as $box) {
                if ($item->getWeight() <= ($box->getMaxWeight() - $box->getEmptyWeight()) && (isset($cache[$cacheKey]) || (count((new OrientatedItemFactory($box))->getPossibleOrientations($item, null, $box->getInnerWidth(), $box->getInnerLength(), $box->getInnerDepth(), 0, 0, 0, new PackedItemList())) > 0))) {
                    $cache[$cacheKey] = true;
                    continue 2;
                }
            }
            $this->unpackedItems->insert($item);
            $this->items->remove($item);
        }
    }
}
