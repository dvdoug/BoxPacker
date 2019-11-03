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
        $itemList = clone $this->items;

        while (true) {
            try {
                return parent::pack();
            } catch (ItemTooLargeException $e) {
                $this->unpackedItems->insert($e->getItem());
                $itemList->remove($e->getItem());
                $this->setItems($itemList);
            }
        }
    }
}
