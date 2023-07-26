Too-large items
===============

As a library, by default BoxPacker makes the design choice that any errors or exceptions thrown during operation are
best handled by you and your own code as the appropriate way to to handle a failure will vary from application to application.
There is no attempt made to handle/recover from them internally.

This includes the case where there are no boxes large enough to pack a particular item. The normal operation of the Packer
class is to throw an ``NoBoxesAvailableException``. If your application has well-defined logging and monitoring it may be
sufficient to simply allow the exception to bubble up to your generic handling layer and handle like any other runtime failure.
Applications that do that can make an assumption that if no exceptions were thrown, then all items were successfully
placed into a box.

Alternatively, you might wish to catch the exception explicitly and have domain-specific handling logic e.g.

.. code-block:: php

    <?php
        use DVDoug\BoxPacker\NoBoxesAvailableException;
        use DVDoug\BoxPacker\Packer;
        use DVDoug\BoxPacker\Test\TestBox;  // use your own `Box` implementation
        use DVDoug\BoxPacker\Test\TestItem; // use your own `Item` implementation

        try {
            $packer = new Packer();

            $packer->addBox(new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000));
            $packer->addBox(new TestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000));

            $packer->addItem(new TestItem('Item 1', 2500, 2500, 20, 2000, Rotation::BestFit));
            $packer->addItem(new TestItem('Item 2', 25000, 2500, 20, 2000, Rotation::BestFit));
            $packer->addItem(new TestItem('Item 3', 2500, 2500, 20, 2000, Rotation::BestFit));

            $packedBoxes = $packer->pack();
        } catch (NoBoxesAvailableException $e) {
            $problemItem = $e->getItem(); //the custom exception allows you to retrieve the affected item
            // pause dispatch, email someone or any other handling of your choosing
        }

However, an ``Exception`` is for exceptional situations and for some businesses, some items being too large and thus
requiring special handling might be considered a normal everyday situation. For these applications, having an
``Exception`` thrown which interrupts execution might be not be wanted or be considered problematic.

BoxPacker also supports this workflow with the ``InfalliblePacker``. This class extends the base ``Packer`` and automatically
handles any ``NoBoxesAvailableException``. It can be used like this:

.. code-block:: php

    <?php
        use DVDoug\BoxPacker\InfalliblePacker;
        use DVDoug\BoxPacker\Test\TestBox;  // use your own `Box` implementation
        use DVDoug\BoxPacker\Test\TestItem; // use your own `Item` implementation

        $packer = new InfalliblePacker();

        $packer->addBox(new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000));
        $packer->addBox(new TestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000));

        $packer->addItem(new TestItem('Item 1', 2500, 2500, 20, 2000, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 2', 25000, 2500, 20, 2000, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 3', 2500, 2500, 20, 2000, Rotation::BestFit));

        $packedBoxes = $packer->pack(); //same as regular Packer

        // It is *very* important to check this is an empty list (or not) when exceptions are disabled!
        $unpackedItems = $packer->getUnpackedItems();
