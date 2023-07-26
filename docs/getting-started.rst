Getting started
===============

BoxPacker is designed to integrate as seamlessly as possible into your existing systems, and therefore makes strong use of
PHP interfaces. Applications wanting to use this library will typically already have PHP domain objects/entities representing
the items needing packing, so BoxPacker attempts to take advantage of these as much as possible by allowing you to pass them
directly into the Packer rather than needing you to construct library-specific datastructures first. This also makes it much
easier to work with the output of the Packer - the returned list of packed items in each box will contain your own objects,
not simply references to them so if you want to calculate value for insurance purposes or anything else this is easy to do.

Similarly, although it's much more uncommon to already have 'Box' objects before implementing this library, you'll typically
want to implement them in an application-specific way to allow for storage/retrieval from a database. The Packer also allows
you to pass in these objects directly too.

To accommodate the wide variety of possible object types, the library defines two interfaces ``BoxPacker\Item`` and
``BoxPacker\Box`` which define methods for retrieving the required dimensional data - e.g. ``getWidth()``. There's a good chance
you may already have at least some of these defined. ::

              --------------
             /            /|
            /            / |
           /            /  |
          |------------/   |
          |            |   /
          |            |  /
   depth  |            | / length
          |            |/
          |------------/
               width

If you do happen to have methods defined with those names already, **and they are incompatible with the interface expectations**,
then this will be only case where some kind of wrapper object would be needed.

Examples
--------

Packing a set of items into a given set of box types
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: php

    <?php
        use DVDoug\BoxPacker\Rotation;
        use DVDoug\BoxPacker\Packer;
        use DVDoug\BoxPacker\Test\TestBox;  // use your own `Box` implementation
        use DVDoug\BoxPacker\Test\TestItem; // use your own `Item` implementation

        $packer = new Packer();

        /*
         * Add choices of box type - in this example the dimensions are passed in directly via constructor,
         * but for real code you would probably pass in objects retrieved from a database instead
         */
        $packer->addBox(
            new TestBox(
                reference: 'Le petite box',
                outerWidth: 300,
                outerLength: 300,
                outerDepth: 10,
                emptyWeight: 10,
                innerWidth: 296,
                innerLength: 296,
                innerDepth: 8,
                maxWeight: 1000
            )
        );
        $packer->addBox(
            new TestBox(
                reference: 'Le grande box',
                outerWidth: 3000,
                outerLength: 3000,
                outerDepth: 100,
                emptyWeight: 100,
                innerWidth: 2960,
                innerLength: 2960,
                innerDepth: 80,
                maxWeight: 10000
            )
        );

        /*
         * Add items to be packed - e.g. from shopping cart stored in user session. Again, the dimensional information
         * (and keep-flat requirement) would normally come from a DB
         */
        $packer->addItem(
            item: new TestItem(
                description: 'Item 1',
                width: 250,
                length: 250,
                depth: 12,
                weight: 200,
                allowedRotation: Rotation::KeepFlat
            ),
            qty: 1
        );
        $packer->addItem(
            item: new TestItem(
                description: 'Item 2',
                width: 250,
                length: 250,
                depth: 12,
                weight: 200,
                allowedRotation: Rotation::KeepFlat
            ),
            qty: 2
        );
        $packer->addItem(
            item: new TestItem(
                description: 'Item 3',
                width: 250,
                length: 250,
                depth: 24,
                weight: 200,
                allowedRotation: Rotation::BestFit
            ),
            qty: 1
        );

        $packedBoxes = $packer->pack();

        echo "These items fitted into " . count($packedBoxes) . " box(es)" . PHP_EOL;
        foreach ($packedBoxes as $packedBox) {
            $boxType = $packedBox->getBox(); // your own box object, in this case TestBox
            echo "This box is a {$boxType->getReference()}, it is {$boxType->getOuterWidth()}mm wide, {$boxType->getOuterLength()}mm long and {$boxType->getOuterDepth()}mm high" . PHP_EOL;
            echo "The combined weight of this box and the items inside it is {$packedBox->getWeight()}g" . PHP_EOL;

            echo "The items in this box are:" . PHP_EOL;
            $packedItems = $packedBox->getItems();
            foreach ($packedItems as $packedItem) { // $packedItem->getItem() is your own item object, in this case TestItem
                echo $packedItem->getItem()->getDescription() . PHP_EOL;
            }
        }

Does a set of items fit into a particular box
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
.. code-block:: php

    <?php
        use DVDoug\BoxPacker\Rotation;
        use DVDoug\BoxPacker\Packer;
        use DVDoug\BoxPacker\Test\TestBox;  // use your own `Box` implementation
        use DVDoug\BoxPacker\Test\TestItem; // use your own `Item` implementation

        /*
         * To just see if a selection of items will fit into one specific box
         */
        $box = new TestBox(
            reference: 'Le box',
            outerWidth: 300,
            outerLength: 300,
            outerDepth: 10,
            emptyWeight: 10,
            innerWidth: 296,
            innerLength: 296,
            innerDepth: 8,
            maxWeight: 1000
        );

        $items = new ItemList();
        $items->insert(
            new TestItem(
                description: 'Item 1',
                width: 297,
                length: 296,
                depth: 2,
                weight: 200,
                allowedRotation: Rotation::BestFit
            )
        );
        $items->insert(
            new TestItem(
                description: 'Item 2',
                width: 297,
                length: 296,
                depth: 2,
                weight: 500,
                allowedRotation: Rotation::BestFit
            )
        );
        $items->insert(
            new TestItem(
                description: 'Item 3',
                width: 296,
                length: 296,
                depth: 4,
                weight: 290,
                allowedRotation: Rotation::BestFit
            )
        );

        $volumePacker = new VolumePacker($box, $items);
        $packedBox = $volumePacker->pack(); //$packedBox->getItems() contains the items that fit
