What's new / Upgrading
======================

.. note::

     Below is summary of key changes between versions that you should be aware of. A full changelog, including changes in minor
     versions is available from https://github.com/dvdoug/BoxPacker/blob/master/CHANGELOG.md

Version 3
---------

Positional information on packed items
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
Version 3 allows you to see the positional and dimensional information of each item as packed. Exposing this additional data
unfortunately means an API change - specifically ``PackedBox->getItems`` now returns a set of ``PackedItem`` s rather than
``Item`` s. A ``PackedItem`` is a wrapper around around an `Item` with positional and dimensional information
(x/y/z co-ordinates of corner closest to origin, width/length/depth as packed). Adapting existing v2 code to v3 is simple:

Before

.. code-block:: php

    <?php
        $itemsInTheBox = $packedBox->getItems();
        foreach ($itemsInTheBox as $item) { // your own item object
            echo $item->getDescription() . PHP_EOL;
        }

After

.. code-block:: php

    <?php
        $packedItems = $packedBox->getItems();
        foreach ($packedItems as $packedItem) { // $packedItem->getItem() is your own item object
            echo $packedItem->getItem()->getDescription() . PHP_EOL;
        }

If you use ``BoxPacker\ConstrainedItem``, you'll need to make the same change there too.

PHP 7 type declarations
^^^^^^^^^^^^^^^^^^^^^^^
Version 3 also takes advantage of the API break opportunity introduced by the additional positional information and is the first
version of BoxPacker to take advantage of PHP7's type declaration system. The core ``BoxPacker\Item`` and ``BoxPacker\Box``
interfaces definitions have been supplemented with code-level type information to enforce expectations. This is a technical break
only, no implementation requires changing - only the correct type information added, e.g.

Before

.. code-block:: php

    <?php
        /**
         * @return string
         */
        public function getDescription()
        {
            return $this->description;
        }

After

.. code-block:: php

    <?php
        /**
         * @return string
         */
        public function getDescription(): string
        {
            return $this->description;
        }

Version 2
---------

3D rotation when packing
^^^^^^^^^^^^^^^^^^^^^^^^
Version 2 of BoxPacker introduces a key feature for many use-cases, which is support for full 3D rotations of items. Version 1
was limited to rotating items in 2D only - effectively treating every item as "keep flat" or "ship this way up". Version 2
adds an extra method onto the ``BoxPacker\Item`` interface to control on a per-item level whether the item can be turned onto
it's side or not.

Removal of deprecated methods
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
The ``packIntoBox``, ``packBox`` and ``redistributeWeight`` methods were removed from the ``Packer`` class. If you were previously
using these v1 methods, please see their implementations in https://github.com/dvdoug/BoxPacker/blob/1.x-dev/Packer.php for a
guide on how to achieve the same results with v2.
