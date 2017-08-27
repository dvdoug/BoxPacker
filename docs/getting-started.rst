Getting started
===============

BoxPacker is designed to integrate as seamlessly as possible into your existing systems, and therefore makes strong use of
PHP interfaces. Applications wanting to use this library will typically already have PHP domain objects/entities representing
the items needing packing, so BoxPacker attempts to take advantage of these as much as possible by allowing you to pass them
directly into the Packer rather than needing you to construct library-specific datastructures first. This also makes it much
easier to work with the output of the Packer - the returned list of packed items in each box will contain your own objects,
not simply references to them.

Similarly, although it's much more uncommon to already have 'Box' objects before implementing this library, you'll typically
want to implement them in an application-specific way to allow for storage/retrieval from a database. The Packer also allows
you to pass in these objects directly too.

To accommodate the wide variety of possible object types, the library defines two interfaces ``BoxPacker\Item`` and
``BoxPacker\Box`` which define methods for retrieving the required dimensional data - e.g. ``getWidth()``. There's a good chance
you may already have these defined.

If you do happen to have methods defined with those names already, **and they are incompatible with the interface expectations**,
then this will be only case where some kind of wrapper object.

Examples
--------

Packing a set of items into a given set of box types
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: php

    <?php
        use DVDoug\BoxPacker\Packer;
        use DVDoug\BoxPacker\Test\TestBox;  // use your own object
        use DVDoug\BoxPacker\Test\TestItem; // use your own object

        $packer = new Packer();

        /*
         * Add choices of box type - in this example the dimensions are passed in directly via constructor,
         * but for real code you would probably pass in objects retrieved from a database instead
         */
        $packer->addBox(new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000));
        $packer->addBox(new TestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000));

        /*
         * Add items to be packed - e.g. from shopping cart stored in user session
         */
        $packer->addItem(new TestItem('Item 1', 250, 250, 12, 200));
        $packer->addItem(new TestItem('Item 2', 250, 250, 12, 200));
        $packer->addItem(new TestItem('Item 3', 250, 250, 24, 200));

        $packedBoxes = $packer->pack();

        echo "These items fitted into " . count($packedBoxes) . " box(es)" . PHP_EOL;
        foreach ($packedBoxes as $packedBox) {
            $boxType = $packedBox->getBox(); // your own box object, in this case TestBox
            echo "This box is a {$boxType->getReference()}, it is {$boxType->getOuterWidth()}mm wide, {$boxType->getOuterLength()}mm long and {$boxType->getOuterDepth()}mm high" . PHP_EOL;
            echo "The combined weight of this box and the items inside it is {$packedBox->getWeight()}g" . PHP_EOL;

            echo "The items in this box are:" . PHP_EOL;
            $itemsInTheBox = $packedBox->getItems();
            foreach ($itemsInTheBox as $item) { // your own item object, in this case TestItem
                echo $item->getDescription() . PHP_EOL;
            }
        }

Does a set of items fit into a particular box
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
