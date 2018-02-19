Too-large items
===============

As a library, by default BoxPacker makes the design choice that any errors or exceptions thrown during operation are
best handled by you and your own code as the appropriate way to to handle a failure will vary from application to application.
There is no attempt made to handle/recover from them internally.

This includes the case where there are no boxes large enough to pack a particular item. The normal operation of the Packer
class is to throw an ``ItemTooLargeException``. If your application has well-defined logging and monitoring it may be
sufficient to simply allow the exception to bubble up to your generic handling layer and handle like any other runtime failure.
Alternatively, you may wish to catch it explicitly and have domain-specific handling logic e.g.

.. code-block:: php

    <?php
        use DVDoug\BoxPacker\ItemTooLargeException;
        use DVDoug\BoxPacker\Packer;
        use DVDoug\BoxPacker\Test\TestBox;  // use your own object
        use DVDoug\BoxPacker\Test\TestItem; // use your own object

        try {
            $packer = new Packer();

            $packer->addBox(new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000));
            $packer->addBox(new TestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000));

            $packer->addItem(new TestItem('Item 1', 2500, 2500, 20, 2000, true));
            $packer->addItem(new TestItem('Item 2', 25000, 2500, 20, 2000, true));
            $packer->addItem(new TestItem('Item 3', 2500, 2500, 20, 2000, true));

            $packedBoxes = $packer->pack();
        } catch (ItemTooLargeException $e) {
            $problemItem = $e->getItem(); //the custom exception allows you to retrieve the affected item
            // pause dispatch, email someone or any other handling of your choosing
        }

For some applications the ability/requirement to do their own handling of this case may not be wanted or may even be
problematic, e.g. if some items being too large and requiring special handling is a normal situation for that particular business.

BoxPacker also supports this workflow with the ``InfalliblePacker``. This class extends the base ``Packer`` and automatically
handles any ``ItemTooLargeExceptions``. It can be used like this:

.. code-block:: php

    <?php
        use DVDoug\BoxPacker\InfalliblePacker;
        use DVDoug\BoxPacker\Test\TestBox;  // use your own object
        use DVDoug\BoxPacker\Test\TestItem; // use your own object

        $packer = new InfalliblePacker();

        $packer->addBox(new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000));
        $packer->addBox(new TestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000));

        $packer->addItem(new TestItem('Item 1', 2500, 2500, 20, 2000, true));
        $packer->addItem(new TestItem('Item 2', 25000, 2500, 20, 2000, true));
        $packer->addItem(new TestItem('Item 3', 2500, 2500, 20, 2000, true));

        $packedBoxes = $packer->pack(); //same as regular Packer
        $unpackedItems = $packer->getUnpackedItems();
