BoxPacker
=========

(What will be!) An implementation of 3D bin packing/knapsack problem.

[Wikipedia](http://en.wikipedia.org/wiki/Bin_packing_problem) says this is NP-hard, and there is no way to always achieve an
optimum solution without running through every single permutation. But
that's OK because this implementation is designed to simulate a naive human
approach to the problem rather than search for the "perfect" solution.

This is for 2 reasons:

1. It's quicker
2. It doesn't require the person packing the box to be given a 3D diagram
   explaining just how the items are supposed to fit in the box.

Principles
----------

 * Pack largest (by volume) items first
 * Pack vertically up the side of the box
 * Exception is where the item under consideration is half-size or less than
   the item underneath it. Then pack side-by-side. Again, prefer mini-stacks
   where possible
 * Only very small overhangs are allowed (25%) to prevent items bending in
   transit. The available width/height for each layer will normally decrease
   as the stack of items gets taller.
 * If more then 1 box is needed to accomodate all of the items, then the    
   
  
Constraints
-----------

 * My use case for this code is a store that sell publications, which need to
   be shipped flat. The algorithm as implemented therefore considers simple 2D
   rotation of items when determining fit but will not try turning items on
   their side.
 * The algorithm does consider spatial constraints in all 3 dimensions, plus
   consideration of weight.

Requirements
------------

* PHP version 5.3 or higher (it may work on earlier versions, but has not been tested) 

License
-------
BoxPacker is MIT-licensed. 
