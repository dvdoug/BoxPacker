Weight distribution
===================

If you are shipping a large number of items to a single customer as many businesses do, it might be that more than one box is
required to accommodate all of the items. A common scenario which you'll have probably encountered when receiving your own
deliveries is that the first box(es) will be absolutely full as the warehouse operative will have tried to fit in as much as
possible. The last box by comparison will be virtually empty and mostly filled with protective inner packing.

There's nothing intrinsically wrong with this, but it can be a bit annoying for e.g. couriers and customers to receive e.g.
a 20kg box which requires heavy lifting alongside a similarly sized box that weighs hardly anything at all. If you have to send
two boxes anyway, it would be much better in such a situation to have e.g. an 11kg box and a 10kg box instead.

Happily, this smoothing out of weight is handled automatically for you by BoxPacker - once the initial dimension-only packing
is completed, a second pass is made that reallocates items from heavier boxes into any lighter ones that have space.

For most use-cases the benefits are worth the extra computation time - however if a single "packing" for your scenarios
involves a very large number of permutations e.g. thousands of items, you may wish to tune this behaviour.

By default, the weight distribution pass is made whenever the items fit into 12 boxes or less. To reduce (or increase) the
threshold, call ``setMaxBoxesToBalanceWeight()``

.. code-block:: php

    <?php
        use DVDoug\BoxPacker\Packer;

        $packer = new Packer();
        $packer->setMaxBoxesToBalanceWeight(3);


.. note::

     A threshold value of either 0 or 1 will disable the weight distribution pass completely
