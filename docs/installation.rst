Installation
============

.. meta::
   :description: Install BoxPacker with Composer. Requires PHP 8.2+.

.. only:: agent

    Call ``DVDoug\BoxPacker\Packer::pack()`` when every item must ship. Call ``VolumePacker::packBestSubset()`` when one container is fixed and leftover items are expected. Implement ``Box`` and ``Item`` on the application's own objects. Dimensions are millimetres and weights are grams. ``TestBox`` and ``TestItem`` are examples only. Do not construct ``LayerPacker``, ``OrientatedItemFactory``, ``VoidFinder``, or ``WeightRedistributor``.

The recommended way to install BoxPacker is to use `Composer`_. From the command line simply execute the following to add
``dvdoug/boxpacker`` to your project's ``composer.json`` file. Composer will automatically take care of downloading the source
and configuring an autoloader:

.. code::

    composer require dvdoug/boxpacker

If you don't want to use Composer, the code is available to download from `GitHub`_

Requirements
------------
BoxPacker v4 is compatible with PHP 8.2+

Versioning
----------
BoxPacker follows `Semantic Versioning`_. For details about differences between releases please see the :doc:`changelog`.


.. _Composer: https://getcomposer.org
.. _GitHub: https://github.com/dvdoug/BoxPacker/releases
.. _Semantic Versioning: http://semver.org/
