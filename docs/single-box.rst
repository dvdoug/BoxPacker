Filling one container
=====================

.. meta::
   :description: Fill one fixed container as densely as possible with VolumePacker::packBestSubset().

``Packer::pack()`` assumes the whole consignment has to leave today: if it
will not fit in one carton, another is opened. That is the right default for
e-commerce fulfilment.

Sometimes the constraint is the other way around. The container is fixed, and
the warehouse has *more* cargo than will fit. You want this sailing, this
trailer, or this pallet as full as possible; whatever is left waits for the
next one. Typical cases:

* An ocean container or swap-body you have already paid for, loaded from a
  yard of outbound stock
* A store-replenishment pallet or roll-cage with a hard size limit
* A single van or trailer run, where a second trip is worse than leaving a
  few lines behind

``VolumePacker::packBestSubset()`` is for that situation. It still packs into
one box you supply. It is allowed to leave items out if that produces a
fuller load — for example skipping one bulky piece so several smaller ones
can take its place.

.. code-block:: php

    <?php
        use DVDoug\BoxPacker\ItemList;
        use DVDoug\BoxPacker\Rotation;
        use DVDoug\BoxPacker\VolumePacker;
        use DVDoug\BoxPacker\Test\TestBox;  // use your own `Box` implementation
        use DVDoug\BoxPacker\Test\TestItem; // use your own `Item` implementation

        $container = new TestBox('40ft', 1203, 235, 269, 0, 1203, 235, 269, 26000000);
        $yard = new ItemList();
        $yard->insert(new TestItem('Pallet A', 120, 80, 100, 500000, Rotation::BestFit), 8);
        $yard->insert(new TestItem('Crate B', 60, 40, 40, 80000, Rotation::BestFit), 40);

        $loaded = (new VolumePacker($container, $yard))->packBestSubset();
        // $loaded->items went on this departure
        // anything not in that list stays in the yard

Use ``Packer::pack()`` when every item on the order must ship: another box is
used when one is full. Use ``VolumePacker::pack()`` to fill the single box you
give it, stopping when the next item does not fit. Use
``VolumePacker::packBestSubset()`` when skipping a bulky item would leave that
box fuller.

.. warning::

    ``packBestSubset()`` does more work than ``VolumePacker::pack()`` because it considers
    leaving items behind. Keep it for one container and a known pool of
    cargo, not as a substitute for packing a full day's orders.
