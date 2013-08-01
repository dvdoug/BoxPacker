BoxPacker
=========

An implementation of 3D bin packing/knapsack problem.

[![Build Status](https://travis-ci.org/dvdoug/BoxPacker.png)](https://travis-ci.org/dvdoug/BoxPacker)

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
 * [TODO] Exception is where the item under consideration is half-size or less than
   the item underneath it. Then pack side-by-side. Again, prefer mini-stacks
   where possible
 * [TODO] Only very small overhangs are allowed (25%) to prevent items bending in
   transit. The available width/height for each layer will normally decrease
   as the stack of items gets taller.
 * [TODO] If more then 1 box is needed to accommodate all of the items, then aim for
   boxes of roughly equal weight
   
  
Constraints
-----------

 * My use case for this code is a store that sells books, which need to
   be shipped flat. The algorithm as implemented therefore considers simple 2D
   rotation of items when determining fit but will not try turning items on
   their side
 * The algorithm does consider spatial constraints in all 3 dimensions, plus
   consideration of weight
 * The code is beta, use at your own risk

 Usage
 -----
 Just make your items implement the BoxPacker\Item and BoxPacker\Box interfaces, and then:

```php

  /*
   * To figure out which boxes you need, and which items go into which box
   */
  $packer = new Packer();
  $packer->addBox(new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000));
  $packer->addBox(new TestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000));
  $packer->addItem(new TestItem('Item 1', 250, 250, 2, 200));
  $packer->addItem(new TestItem('Item 2', 250, 250, 2, 200));
  $packer->addItem(new TestItem('Item 3', 250, 250, 2, 200));
  $packedBoxes = $packer->pack();

  /*
   * To just see if items will fit into a specific size of box
   */
  $box = new TestBox('Le box', 300, 300, 10, 10, 296, 296, 8, 1000);

  $items = new ItemList;
  $items->insert(new TestItem('Item 1', 297, 296, 2, 200));
  $items->insert(new TestItem('Item 2', 297, 296, 2, 500));
  $items->insert(new TestItem('Item 3', 296, 296, 4, 290));

  $packer = new Packer();
  $packedItems = $packer->packBox($box, $items);

```

Requirements
------------

* PHP version 5.3 or higher

License
-------
BoxPacker is MIT-licensed. 
