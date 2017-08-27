What's new / Upgrading
======================

.. note::

     Below is summary of important changes between versions. A full changelog, including changes in versions not yet
     released is available from https://github.com/dvdoug/BoxPacker/blob/master/CHANGELOG.md

Version 2
---------

3D rotation when packing
^^^^^^^^^^^^^^^^^^^^^^^^
Version 2 of BoxPacker introduces a key feature for many usecases, which is support for full 3D rotations of items. Version 1
was limited to rotating items in 2D only - effectively treating every item as "keep flat" or "ship this way up". Version 2
adds an extra method onto the ``BoxPacker\Item`` interface to control on a per-item level whether the item can be turned onto
it's side or not.

Removal of deprecated methods
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
The ``packIntoBox``, ``packBox`` and ``redistributeWeight`` methods were removed from the ``Packer`` class. If you were previously
using these v1 methods, please see their implementations in https://github.com/dvdoug/BoxPacker/blob/1.x-dev/Packer.php for a
guide on how to achieve the same results with v2.
