BoxPacker
=========

An implementation of the 3D bin packing/knapsack problem i.e. given a list of items, how many boxes do you need to fit
them all in.

Especially useful for e.g. e-commerce contexts when you need to know box size/weight to calculate shipping costs.

[![Build Status](https://travis-ci.org/dvdoug/BoxPacker.svg?branch=master)](https://travis-ci.org/dvdoug/BoxPacker)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/dvdoug/BoxPacker/badges/quality-score.png?b=2.x-dev)](https://scrutinizer-ci.com/g/dvdoug/BoxPacker/?branch=2.x-dev)
[![Download count](https://img.shields.io/packagist/dt/dvdoug/boxpacker.svg)](https://packagist.org/packages/dvdoug/boxpacker)
[![Download count](https://img.shields.io/packagist/v/dvdoug/boxpacker.svg)](https://packagist.org/packages/dvdoug/boxpacker)


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


Installation
------------
If you use [Composer](http://getcomposer.org/), just add `dvdoug/boxpacker` to your project's `composer.json` file:
```json
    {
        "require": {
            "dvdoug/boxpacker": "^2.3.0"
        }
    }
```

Otherwise, the library is PSR-4 compliant, so will work with the autoloader of your choice.

Usage
-----
BoxPacker is designed to integrate as seamlessly as possible into your existing systems. To use the library, you will
need to implement the `BoxPacker\Item` interface on your item/product objects and `BoxPacker\Box` on the objects you use to to represent a box.
These interfaces are quite minimal, but provide a standardised way for the packing process to obtain the dimensional information it needs in order to work.

Basic usage then looks something like the below:
(although you'd probably want to do something more useful with the results than just output to the screen, and your dimensional data would hopefully come from a database!)

```php

  use DVDoug\BoxPacker\Packer;
  use DVDoug\BoxPacker\TestBox;  // use your own implementation
  use DVDoug\BoxPacker\TestItem; // use your own implementation

  /*
   * To figure out which boxes you need, and which items go into which box
   */
  $packer = new Packer();
  $packer->addBox(new TestBox('Le petite box', 300, 300, 10, 10, 296, 296, 8, 1000));
  $packer->addBox(new TestBox('Le grande box', 3000, 3000, 100, 100, 2960, 2960, 80, 10000));
  $packer->addItem(new TestItem('Item 1', 250, 250, 2, 200, true));
  $packer->addItem(new TestItem('Item 2', 250, 250, 2, 200, true));
  $packer->addItem(new TestItem('Item 3', 250, 250, 2, 200, true));
  $packedBoxes = $packer->pack();

  echo("These items fitted into " . count($packedBoxes) . " box(es)" . PHP_EOL);
  foreach ($packedBoxes as $packedBox) {
    $boxType = $packedBox->getBox(); // your own box object, in this case TestBox
    echo("This box is a {$boxType->getReference()}, it is {$boxType->getOuterWidth()}mm wide, {$boxType->getOuterLength()}mm long and {$boxType->getOuterDepth()}mm high" . PHP_EOL);
    echo("The combined weight of this box and the items inside it is {$packedBox->getWeight()}g" . PHP_EOL);

    echo("The items in this box are:" . PHP_EOL);
    $itemsInTheBox = $packedBox->getItems();
    foreach ($itemsInTheBox as $item) { // your own item object, in this case TestItem
      echo($item->getDescription() . PHP_EOL);
    }

    echo(PHP_EOL);
  }



  /*
   * To just see if a selection of items will fit into one specific box
   */
  $box = new TestBox('Le box', 300, 300, 10, 10, 296, 296, 8, 1000);

  $items = new ItemList();
  $items->insert(new TestItem('Item 1', 297, 296, 2, 200, true));
  $items->insert(new TestItem('Item 2', 297, 296, 2, 500, true));
  $items->insert(new TestItem('Item 3', 296, 296, 4, 290, true));

  $volumePacker = new VolumePacker($box, $items);
  $packedBox = $volumePacker->pack();
  /* $packedBox->getItems() contains the items that fit */
```

BoxPacker is designed to run calculations as efficiently as possible, the 9100+ tests in the test suite run in 41
seconds on my workstation, giving a rate of 220+ solutions/second which should be more than sufficient for
most e-commerce stores :) If you do wish to benchmark the library to evaluate performance in your own scenarios, please
disable Xdebug when doing so - in my experience the unit tests take 24x longer (41 sec->16 min) when Xdebug is loaded.

Advanced Usage
--------------
For more advanced use cases where greater control over the contents of each box is required (e.g. legal limits on the
number of hazardous items per box), you may implement the `BoxPacker\ConstrainedItem` interface which contains an
additional callback method allowing you to decide whether an item may be packed into a box given it's existing contents.

Requirements
------------

* PHP version 5.4 or higher (including PHP7 and HHVM)

License
-------
BoxPacker is MIT-licensed. 
