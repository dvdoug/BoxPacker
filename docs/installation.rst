Installation
============

The recommended way to install BoxPacker is to use `Composer`_. From the command line simply execute the following to add
``dvdoug/boxpacker`` to your project's ``composer.json`` file. Composer will automatically take care of downloading the source
and configuring an autoloader:

.. code::

    composer require dvdoug/boxpacker

If you don't want to use Composer, the code is available to download from `GitHub`_

Requirements
------------
BoxPacker v4 is compatible with PHP 7.3+

.. note::

    Still running an older version of PHP? No problem! BoxPacker v3 is compatible with PHP 7.1 and up.

    v3 is still maintained and uses the same core packing algorithm
    as v4, however lack certain features which are not possible to implement in a backwards-compatible manner.

Versioning
----------
BoxPacker follows `Semantic Versioning`_. For details about differences between releases please see `What's new`_


.. _Composer: https://getcomposer.org
.. _GitHub: https://github.com/dvdoug/BoxPacker/releases
.. _Semantic Versioning: http://semver.org/
.. _What's new: whatsnew.html
