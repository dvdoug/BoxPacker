.. _sortation:

Sortation
=========

BoxPacker (mostly) uses "online" algorithms, that is it packs sequentially, with no regard for what comes next.
Therefore the order of items, or the order of boxes are of crucial importance in obtaining good results.

By default, BoxPacker will try to be as smart as possible about this, packing larger/heavier items into the bottom
of a box, with smaller/lighter items that might get crushed placed above them. It will also prefer to use smaller
boxes where possible, rather than larger ones.

However, BoxPacker also allows you to influence many of these decisions if you prefer.

Items
-----
You may wish to explicitly pack heavier items before larger ones. Or larger ones before heavier ones. Or prefer to keep
items of a similar "group" together (whatever that might mean for your application). The ``ItemList`` class supports
this via two methods.

Supplying a pre-sorted list
^^^^^^^^^^^^^^^^^^^^^^^^^^^
If you already have your items in a pre-sorted array (e.g. when using a database ``ORDER BY``, you can construct an
``ItemList`` directly from it. You can also use this mechanism if you know that all of your items have identical
dimensions and therefore having BoxPacker sort them before commencing packing would just be a waste of CPU time.

.. code-block:: php

    $itemList = ItemList::fromArray($anArrayOfItems, true); // set the 2nd param to true if presorted

Overriding the default algorithm
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
First, create your own implementation of the ``ItemSorter`` interface implementing your particular requirements:

.. code-block:: php

    /**
     * A callback to be used with usort(), implementing logic to determine which Item is a higher priority for packing.
     */
    YourApplicationItemSorter implements DVDoug\BoxPacker\ItemSorter
    {
        /**
         * Return -1 if $itemA is preferred, 1 if $itemB is preferred or 0 if neither is preferred.
         */
        public function compare(Item $itemA, Item $itemB): int
        {
            // your logic to determine ordering goes here. Remember, that Item is your own object,
            // and you have full access to all methods on it, not just the ones from the Item interface
        }
    }

Then, pass this to the ``ItemList`` constructor

.. code-block:: php

    $sorter = new YourApplicationItemSorter();
    $itemList = new ItemList($sorter);

Enforcing strict ordering
^^^^^^^^^^^^^^^^^^^^^^^^^
Regardless of which of the above methods you use, BoxPacker's normal mode of operation is to respect the sort ordering
*but not at the expense of packing density*. If an item in the list is too large to fit into a particular space,
BoxPacker will temporarily skip over it and will try the next item in the list instead.

This typically works well for ecommerce, but in some applications you may want your custom sort to be absolutely
determinative. You can do this by calling ``beStrictAboutItemOrdering()``.

.. code-block:: php

    $packer = new Packer();
    $packer->beStrictAboutItemOrdering(true); // or false to turn strict ordering off again

    $volumePacker = new VolumePacker(...);
    $volumePacker->beStrictAboutItemOrdering(true); // or false to turn strict ordering off again

Box types
---------
BoxPacker's default algorithm assumes that box size/weight is a proxy for cost and therefore seeks to use the
smallest/lightest type of box possible for a set of items. However in some cases this assumption might not be true,
or you may have alternate reasons for preferring to use one type of box over another. The ``BoxList`` class supports
this kind of application-controlled sorting via two methods.

Supplying a pre-sorted list
^^^^^^^^^^^^^^^^^^^^^^^^^^^
If you already have your items in a pre-sorted array (e.g. when using a database ``ORDER BY``, you can construct an
``BoxList`` directly from it.

.. code-block:: php

    $boxList = BoxList::fromArray($anArrayOfBoxes, true); // set the 2nd param to true if presorted

Overriding the default algorithm
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
First, create your own implementation of the ``BoxSorter`` interface implementing your particular requirements:

.. code-block:: php

    /**
     * A callback to be used with usort(), implementing logic to determine which Box is "better".
     */
    YourApplicationBoxSorter implements DVDoug\BoxPacker\BoxSorter
    {
        /**
         * Return -1 if $boxA is "best", 1 if $boxB is "best" or 0 if neither is "best".
         */
        public function compare(Box $boxA, Box $boxB): int
        {
            // your logic to determine ordering goes here. Remember, that Box is your own object,
            // and you have full access to all methods on it, not just the ones from the Box interface
        }
    }

Then, pass this to the ``BoxList`` constructor

.. code-block:: php

    $sorter = new YourApplicationBoxSorter();
    $boxList = new BoxList($sorter);

Choosing between permutations
-----------------------------
In a scenario where even the largest box type is not large enough to contain all of the items, BoxPacker needs to decide
which is the "best" possible first box, so it can then pack the remaining items into a second box (and so on). If there
are two different box types that each hold the same number of items (but different items), which one should be picked?
What if one of the boxes can hold an additional item, but is twice as large? Is it better to minimise the number of boxes,
or their volume?

By default, BoxPacker will optimise for the largest number of items in a box, with volume acting as a tie-breaker.
This can also be changed:

Overriding the default algorithm
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
First, create your own implementation of the ``PackedBoxSorter`` interface implementing your particular requirements:

.. code-block:: php

    /**
     * A callback to be used with usort(), implementing logic to determine which PackedBox is "better".
     */
    YourApplicationPackedBoxSorter implements DVDoug\BoxPacker\PackedBoxSorter
    {
        /**
         * Return -1 if $boxA is "best", 1 if $boxB is "best" or 0 if neither is "best".
         */
        public function compare(PackedBox $boxA, PackedBox $boxB): int
        {
            // your logic to determine "best" goes here
        }
    }

Then, pass this to the ``Packer``

.. code-block:: php

    $sorter = new YourApplicationPackedBoxSorter();

    $packer = new Packer();
    $packer->setPackedBoxSorter($sorter);
