BoxPacker
=========

An implementation of the 3D bin packing/knapsack problem i.e. given a list of items, how many boxes do you need to fit
them all in.

Especially useful for e.g. e-commerce contexts when you need to know box size/weight to calculate shipping costs.

[![Build Status](https://travis-ci.org/dvdoug/BoxPacker.png)](https://travis-ci.org/dvdoug/BoxPacker)

[Wikipedia](http://en.wikipedia.org/wiki/Bin_packing_problem) says this is NP-hard, and there is no way to always
achieve an optimum solution without running through every single permutation. But that's OK because this implementation
is designed to simulate a naive human approach to the problem rather than search for the "perfect" solution.

This is for 2 reasons:

1. It's quicker
2. It doesn't require the person actually packing the box to be given a 3D diagram
   explaining just how the items are supposed to fit.

Principles
----------

 * Pack largest (by volume) items first
 * Pack vertically up the side of the box
 * Pack side-by-side where item under consideration fits alongside the previous item
 * Only very small overhangs are allowed (10%) to prevent items bending in transit
 * The available width/height for each layer will therefore decrease as the stack of items gets taller
 * If more than 1 box is needed to accommodate all of the items, then aim for boxes of roughly equal weight
   (e.g. 3 medium size/weight boxes are better than 1 small light box and 2 that are large and heavy)

Constraints
-----------

 * Items are assumed to be shipped flat (e.g. books, fragile items). The algorithm as implemented therefore considers
   simple 2D rotation of items when determining fit but will not try turning items on their side
 * The algorithm does consider spatial constraints in all 3 dimensions, plus consideration of weight

Installation
------------
If you use [Composer](http://getcomposer.org/), just add `dvdoug/boxpacker` to your project's `composer.json` file:
```json
    {
        "require": {
            "dvdoug/boxpacker": "~1.0"
        }
    }
```

Otherwise, the library is PSR-4 compliant, so will work with the autoloader of your choice.

Usage
-----
Just make your items and boxes implement the `BoxPacker\Item` and `BoxPacker\Box` interfaces, and then:

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

BoxPacker is designed to run calculations as efficiently as possible, the 7500+ tests in the test suite run in 14
seconds on my workstation, giving a rate of approx ≈550 solutions/second which should be more than sufficient for
most e-commerce stores :) If you do wish to benchmark the library to evaluate performance in your own scenarios, please
disable Xdebug when doing so - in my experience the unit tests take 32x longer (14sec->460 sec) when Xdebug is loaded.

Requirements
------------

* PHP version 5.4 or higher

License
-------
BoxPacker is MIT-licensed. 
