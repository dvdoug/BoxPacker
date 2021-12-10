Limited supply boxes
====================

In standard/basic use, BoxPacker will assume you have an adequate enough supply of each box type on hand to cover all
eventualities i.e. your warehouse will be very well stocked and the concept of "running low" is not applicable.

However, if you only have limited quantities of boxes available and you have accurate stock control information, you can
feed this information into BoxPacker which will then take it into account so that it won't suggest a packing which would
take you into negative stock.

To do this, have your box objects implement the ``BoxPacker\LimitedSupplyBox`` interface which has a single additional method
over the standard ``BoxPacker\Box`` namely ``getQuantityAvailable()``. The library will automatically detect this and
use the information accordingly.
