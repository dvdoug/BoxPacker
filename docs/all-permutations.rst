Permutations
============

Normally BoxPacker will try and make smart choice(s) of box when building a packing solution that minimise the both the
number used and also their size (see :ref:`Sortation<sortation>`). This is usually the most optimal arrangement from a
logistical point of view, both for efficiency and for shipping cost. Supplying custom sorters as outlined on that page
allow you to influence those decisions if needed.

You may however wish for BoxPacker to not make any of these decisions, but simply have it calculate all\ [#f1]_ of the possible
combinations of box for you to then filter/select inside your own application. You can do this by calling
``packAllPermutations()`` on ``Packer`` instead of ``pack()``:

.. code-block:: php

    <?php
        use DVDoug\BoxPacker\Packer;
        use DVDoug\BoxPacker\Test\TestBox;  // use your own `Box` implementation
        use DVDoug\BoxPacker\Test\TestItem; // use your own `Item` implementation

        $packer = new Packer();

        $packer->addBox(new TestBox('Light box', 100, 100, 100, 1, 100, 100, 100, 100));
        $packer->addBox(new TestBox('Heavy box', 100, 100, 100, 100, 100, 100, 100, 10000));

        $packer->addItem(new TestItem('Item 1', 100, 100, 100, 75, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 2', 100, 100, 100, 75, Rotation::BestFit));
        $packer->addItem(new TestItem('Item 3', 100, 100, 100, 75, Rotation::BestFit));

        $permutations = $packer->packAllPermutations(); // an array of PackedBoxList objects

.. warning::

    Although the regular ``pack()`` does evaluate multiple permutations when calculating its result, it is also able to
    use various optimisation techniques to reduce this to a minimum. By definition, ``packAllPermutations()`` cannot
    take advantage of these, and the number of permutations can easily become **very large** with corresponding
    impacts on runtime. Be absolutely sure you want to use this method, rather than use a custom sorter.

.. rubric:: Footnotes

.. [#f1] "all" refers to the permutations of boxes e.g. 1×large OR 2×medium OR 1×medium + 2×small etc. It does not refer
         to any permutations of items within, since that level of combinatorial explosion would be completely unmanageable
