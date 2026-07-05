Linked items
============

In some scenarios you may have two or more items that must always be packed into the same box — for example, a product and its
mandatory accessory, or a set of items that are sold together and must ship as a unit.

To express this constraint, implement the ``BoxPacker\LinkedItem`` interface on your item class. The interface extends ``BoxPacker\Item``
and adds a single method:

.. code-block:: php

    public function getLinkedItemGroup(): string;

Any items that return the **same non-empty string** from this method are treated as a linked group: the packer guarantees
they will all end up in the same ``PackedBox``.

.. code-block:: php

    <?php
        use DVDoug\BoxPacker\LinkedItem;
        use DVDoug\BoxPacker\Rotation;

        class MyLinkedItem implements LinkedItem
        {
            public function __construct(
                private readonly string $description,
                private readonly int $width,
                private readonly int $length,
                private readonly int $depth,
                private readonly int $weight,
                private readonly Rotation $allowedRotation,
                private readonly string $linkedItemGroup,
            ) {}

            // ... standard Item methods ...

            public function getLinkedItemGroup(): string
            {
                return $this->linkedItemGroup;
            }
        }

        $packer = new Packer();
        $packer->addBox(new MyBox(...));

        // item1 and item2 share the same group identifier and will always be packed together
        $packer->addItem(new MyLinkedItem('Product', ..., linkedItemGroup: 'order-line-42'));
        $packer->addItem(new MyLinkedItem('Accessory', ..., linkedItemGroup: 'order-line-42'));

        $packedBoxes = $packer->pack();

Quantities
----------

If you call ``addItem($linkedItem, qty: N)`` for a ``LinkedItem``, all N inserted instances share the same group
identifier and are therefore all considered part of the same linked group. They will all be packed into the same box.

.. code-block:: php

    $packer->addItem(new MyLinkedItem('Part', ..., linkedItemGroup: 'kit-99'), 4);
    // All 4 instances are part of group 'kit-99' and will always ship together

Non-adjacency
-------------

The linked-group guarantee is about **box assignment only**. Items in a linked group will be in the same ``PackedBox``,
but the packer does **not** guarantee that they are physically adjacent or contiguous inside that box. If you additionally
need adjacency, implement :doc:`custom-constraints` alongside ``LinkedItem``.

Behaviour when a linked group cannot fit
-----------------------------------------

If no available box can hold all members of a linked group simultaneously, the packer treats the whole group as
unpackable — the same way it handles any individual item that is too large to fit. The behaviour is controlled by
``throwOnUnpackableItem()``:

- ``throwOnUnpackableItem(true)`` (the default) — throws ``NoBoxesAvailableException``.
- ``throwOnUnpackableItem(false)`` — the group is skipped; all remaining packable items (including other linked groups
  that *can* fit) are still packed.

Weight redistribution
---------------------

Linked groups are preserved during the automatic :doc:`weight-distribution` pass. The redistributor skips all linked
group members entirely — they are never moved between boxes during weight balancing. This ensures group integrity is
always maintained at the cost of potentially less optimal weight distribution.

Invalid group identifiers
--------------------------

An empty string (``''``) is not a valid group identifier and will cause an ``\InvalidArgumentException`` when the item
is added to the packer. Use a non-empty string such as a UUID, order-line ID, or SKU combination.

Performance note
----------------

As with :doc:`custom-constraints`, only implement ``LinkedItem`` on item classes that genuinely need this feature. The
packer has to perform additional group-completeness checks for every candidate box when linked items are present, which
adds a small overhead compared to packing ordinary items.
