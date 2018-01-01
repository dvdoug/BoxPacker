Principles of operation
=======================

Bin packing is an `NP-hard problem`_ and there is no way to always achieve an optimum solution without running through every
single permutation. But that's OK because this implementation is designed to simulate a naive human approach to the problem
rather than search for the "perfect" solution.

This is for 2 reasons:

1. It's quicker
2. It doesn't require the person actually packing the box to be given a 3D diagram
   explaining just how the items are supposed to fit.

At a high level, the algorithm works like this:

 * Pack largest (by volume) items first
 * Pack vertically up the side of the box
 * Pack side-by-side where item under consideration fits alongside the previous item
 * If more than 1 box is needed to accommodate all of the items, then aim for boxes of roughly equal weight
   (e.g. 3 medium size/weight boxes are better than 1 small light box and 2 that are large and heavy)

.. _NP-hard problem: http://en.wikipedia.org/wiki/Bin_packing_problem
