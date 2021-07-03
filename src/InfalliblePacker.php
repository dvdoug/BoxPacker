<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

/**
 * A version of the packer that swallows internal exceptions.
 *
 * @author Doug Wright
 */
class InfalliblePacker extends Packer
{
    /**
     * @var ItemList
     */
    protected $unpackedItems;

    /**
     * InfalliblePacker constructor.
     */
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
        foreach ($this->items as $item) {
            foreach ($this->boxes as $box) {
                if ($item->getWeight() <= ($box->getMaxWeight() - $box->getEmptyWeight()) && (new OrientatedItemFactory($box))->hasPossibleOrientationsInEmptyBox($item)) {
                    continue 2;
                }
            }
            $this->unpackedItems->insert($item);
            $this->items->remove($item);
        }

        while (true) {
            try {
                return parent::pack();
            } catch (NoBoxesAvailableException $e) {
                $this->unpackedItems->insert($e->getItem());
                $this->items->remove($e->getItem());
            }
        }
    }
}
