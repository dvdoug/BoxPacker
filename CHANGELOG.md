# Changelog

## [3.5.2] - 2020-02-02
### Changed
 - Further optimisation when packing a large number of items

## [3.5.1] - 2020-01-30
### Changed
 - Optimisation when packing a large number of identical items

## [3.5.0] - 2020-01-26
### Added
 - Added a new interface `LimitedSupplyBox extends Box` for situations where there are restrictions on the number of a box type available for packing `Item`s into. The interface contains 1 additional method `getQuantityAvailable()`.
 - Added new exception `NoBoxesAvailableException` which is thrown when an item cannot be packed due to suitable boxes not being available (e.g. when the new functionality is used and the quantity available is insufficient). The existing `ItemTooLargeException` which is thrown when an item is too large to fit into any of the supplied box types at all (regardless of quantity) still exists, and now extends from `NoBoxesAvailableException` as a special case
### Changed
 - Improved efficiency in packing and weight distribution
 - The `ItemList` passed to `VolumePacker`'s constructor is now cloned before usage, leaving the passed-in object unaffected. Previously this was used as a working dataset. The new behaviour aligns with the existing behaviour of `Packer` 
### Fixed
 - Fixed issue where internal sort consistency wasn't always correct
 - Some debug-level logging wasn't logging correctly

## [3.4.1] - 2019-12-21
### Changed
 - Speed improvements

## [3.4.0] - 2019-09-09
### Added
 - Added ability to specify that items are pre-sorted when creating an `ItemList` from an array
### Changed
 - Significant speed improvements when dealing with a large number of items

## [3.3.0] - 2019-07-14
### Added
 - Added `ConstrainedPlacementItem` as a more powerful version of `ConstrainedItem`
### Changed
 - Improved box selection for certain cases
 - Speed improvements
 - Increased detail in debug-level logging
### Deprecated
 - `ConstrainedItem` is now deprecated. Use `ConstrainedPlacementItem` instead

## [3.2.2] - 2018-11-20
### Fixed
 - Fixed divide by zero warning when attempting to pack an item with 0 depth

## [3.2.1] - 2018-11-13
### Fixed
 - Fixed issue where internal sort consistency wasn't always correct

## [3.2.0] - 2018-11-12
### Added
 - Added `getVolume()` helper method to `PackedItem` [Cosmologist]
### Changed
 - Improved item orientation selection for better packing
 - Minor refactorings for code clarity

## [3.1.3] - 2018-06-15
### Changed
 - Worked around an PHP recursion issue when comparing 2 `Item`s.

## [3.1.2] - 2018-06-13
### Changed
 - Fixed typos in documentation code samples

## [3.1.1] - 2018-06-03
### Changed
 - Tweaked composer configuration to make it easier to run the samples in the documentation
 - Minor speed improvements

## [3.1.0] - 2018-02-19
### Added
 - Optional 'Infallible' mode of packing to not throw an exception on error (e.g. item too large) but to continue packing the other items
### Changed
 - Improved stability algorithm
 - Improved box selection for certain cases
 - Some internal refactoring

## [3.0.1] - 2018-01-01
### Added
 - Declare ``PackedBoxList`` as implementing `Countable`
### Changed
 - Improved item orientation selection for better packing

## [3.0.0] - 2017-10-23
### Added
 - Introduced `PackedItem`s which are a wrapper around `Item`s with positional and dimensional information (x, y, z co-ordinates of corner closest to origin, width/length/depth as packed)
 - Added method to set threshold at which weight redistribution is disabled  
### Changed
 - `PackedBox` now contains a `PackedItemList` of `PackedItem`s (rather than an `ItemList` of `Item`s)
 - `ConstrainedItem->canBePackedInBox` now takes a `PackedItemList` of `PackedItem`s (rather than an `ItemList` of `Item`s)
 - `BoxList`, `ItemList`, `PackedBoxList` have been altered to implement the `Traversable` interface rather than extend `SplHeap` directly so that any future changes to the internals will not need an API change  
 - Minimum PHP version is now 7.1
### Removed
 - HHVM support now that project has a stated goal of no longer targeting PHP7 compatibility

## [2.6.5] - 2020-02-02
### Changed
 - Further optimisation when packing a large number of items

## [2.6.4] - 2020-01-30
### Changed
 - Optimisation when packing a large number of identical items

## [2.6.3] - 2020-01-26
### Changed
 - Improved efficiency in packing and weight distribution
 - The `ItemList` passed to `VolumePacker`'s constructor is now cloned before usage, leaving the passed-in object unaffected. Previously this was used as a working dataset. The new behaviour aligns with the existing behaviour of `Packer` 
### Fixed
 - Fixed issue where internal sort consistency wasn't always correct
 - Some debug-level logging wasn't logging correctly

## [2.6.2] - 2019-12-21
### Changed
 - Speed enhancements

## [2.6.1] - 2019-09-15
### Changed
 - Speed enhancements

## [2.6.0] - 2019-07-14
### Added
 - Added `ConstrainedPlacementItem` as a more powerful version of `ConstrainedItem`
### Changed
 - Improved box selection for certain cases
 - Speed improvements
 - Increased detail in debug-level logging
### Deprecated
 - `ConstrainedItem` is now deprecated. Use `ConstrainedPlacementItem` instead

## [2.5.0] - 2018-11-20
### Added
 - Backported positional data support from v3 via new `getPackedItems()` method on `PackedBox`
### Fixed
 - Fixed divide by zero warning when attempting to pack an item with 0 depth

## [2.4.8] - 2018-11-13
### Fixed
 - Fixed issue where internal sort consistency wasn't always correct

## [2.4.7] - 2018-11-12
### Changed
 - Improved item orientation selection for better packing
 - Minor refactorings for code clarity

## [2.4.6] - 2018-06-15
### Changed
 - Worked around an PHP recursion issue when comparing 2 `Item`s.

## [2.4.5] - 2018-06-03
### Changed
 - Tweaked composer configuration to make it easier to run the samples in the documentation

## [2.4.4] - 2018-02-25
### Changed
 - Improved stability algorithm
 - Improved box selection for certain cases
 - Some internal refactoring

## [2.4.3] - 2018-01-01
### Changed
 - Improved item orientation selection for better packing

## [2.4.2] - 2017-10-23
### Changed
 - Previously 2 distinct item types could be mixed when sorting items for packing if they had identical physical dimensions. Now if all dimensions are identical, items are sorted by description so that they are kept together
 
## [2.4.1] - 2017-09-04
### Fixed
 - Used/remaining space calculations were sometimes offset by 90 degrees leading to confusing numbers

## [2.4.0] - 2017-08-14
### Changed
 - Significant reworking of core packing logic to clarify concepts used
### Fixed
 - Fixed issue where `getUsed[Width|Length|Depth]()` could sometimes return an incorrect value

## [2.3.2] - 2017-08-06
### Changed
 - In some cases, complex user-added constraints via `BoxPacker\ConstrainedItem` were not being obeyed
 - Test classes refactored to be autoloadable
 - Some internal refactoring

## [2.3.1] - 2017-04-15
### Changed
 - `PackedBox->getUsedDepth()` could incorrectly return a value of 0 in some situations

## [2.3.0] - 2017-04-09
### Added
 - Add callback system for more complex constraints e.g. max number of hazardous items in a box. To take advantage of the additional flexibility, implement BoxPacker\ConstrainedItem rather than BoxPacker\Item
### Changed
 - Some internal refactoring

## [2.2.1] - 2017-03-12
### Added
 - Added `getItem()` to `ItemTooLargeException` to make it programmatically possible determine what the affected item is

## [2.2.0] - 2017-03-06
### Added
 - The previous limitation that all items were always packed flat has been removed
 - A specific `ItemTooLargeException` exception is now thrown when an item cannot fit inside any boxes rather than a generic `\RuntimeException`

## [2.1.0] - 2017-01-07
### Added
 - Added `getUsed[Width|Length|Depth]()` on PackedBoxes to allow for better visibility into space utilisation
### Changed
 - Equal distribution of weight is now turned off when the number of boxes becomes large as it provides very little to no benefit at that scale and is slow to calculate
 - Various optimisations and internal refactorings

## [2.0.2] - 2016-09-21
### Changed
 - Readme update to reflect v2 API changes

## [2.0.1] - 2016-09-20
### Added
 - Pass on the logger instance from the main Packer class into the helpers
### Changed
 - Allow unit tests to run with standalone PHPUnit

## [2.0] - 2016-05-30
There are no bugfixes or packing logic changes in v2.0 compared to the v1.5.3 release - the bump in version number is purely because the interface changed slightly.
### Added
 - Added a method to the Item interface to specify whether the item should be kept flat or not - this does not do anything yet, but adding now to avoid another major version bump later.
### Changed
 - Various refactorings to split out large functions into more readable pieces
### Removed
 - Removed `Packer->packIntoBox()`, `Packer->packBox()` and `Packer->redistributeWeight()`

## [1.7.2] - 2019-12-21
### Changed
 - Speed enhancements

## [1.7.1] - 2019-09-15
### Changed
 - Speed enhancements

## [1.7.0] - 2019-07-14
### Added
 - Added `ConstrainedPlacementItem` as a more powerful version of `ConstrainedItem`
### Changed
 - Improved box selection for certain cases
 - Speed improvements
 - Increased detail in debug-level logging
### Deprecated
 - `ConstrainedItem` is now deprecated. Use `ConstrainedPlacementItem` instead

## [1.6.9] - 2018-11-20
### Fixed
 - Fixed divide by zero warning when attempting to pack an item with 0 depth

## [1.6.8] - 2018-11-13
### Fixed
 - Fixed issue where internal sort consistency wasn't always correct

## [1.6.7] - 2018-11-12
### Changed
 - Improved item orientation selection for better packing
 - Minor refactorings for code clarity

## [1.6.6] - 2018-06-15
### Changed
 - Worked around an PHP recursion issue when comparing 2 `Item`s.

## [1.6.5] - 2018-06-03
### Changed
 - Tweaked composer configuration to make it easier to run the samples in the documentation

## [1.6.4] - 2018-02-25
### Changed
 - Improved stability algorithm
 - Improved box selection for certain cases
 - Some internal refactoring

## [1.6.3] - 2018-01-01
### Changed
 - Improved item orientation selection for better packing

## [1.6.2] - 2017-10-23
### Changed
 - Previously 2 distinct item types could be mixed when sorting items for packing if they had identical physical dimensions. Now if all dimensions are identical, items are sorted by description so that they are kept together

## [1.6.1] - 2017-09-04
### Fixed
 - Used/remaining space calculations were sometimes offset by 90 degrees leading to confusing numbers

## [1.6.0] - 2017-08-27
API-compatible backport of 2.4.0. All features present except 3D packing.

### Added
 - Added `getUsed[Width|Length|Depth]()` on PackedBoxes to allow for better visibility into space utilisation
 - Added callback system for more complex constraints e.g. max number of hazardous items in a box. To take advantage of the additional flexibility, implement BoxPacker\ConstrainedItem rather than BoxPacker\Item
 - A specific `ItemTooLargeException` exception is now thrown when an item cannot fit inside any boxes rather than a generic `\RuntimeException`
 - Pass on the logger instance from the main Packer class into the helpers
### Changed
 - Significant reworking of core packing logic to clarify concepts used and split out large functions into more readable pieces
 - Test classes refactored to be autoloadable and for unit tests to runnable with standalone PHPUnit
 - Equal distribution of weight is now turned off when the number of boxes becomes large as it provides very little to no benefit at that scale and is slow to calculate

## [1.5.3] - 2016-05-30
### Changed
 - Some refactoring to ease future maintenance

## [1.5.2] - 2016-01-23
### Changed
 - Ensure consistency of packing between PHP 5.x and PHP7/HHVM

## [1.5.1] - 2016-01-03
### Fixed
 - Items were occasionally rotated to fit into space that was actually too small for them [IBBoard]

## [1.5] - 2015-10-13
### Added
 - Added method for retrieving the volume utilisation of a packed box
### Changed
 - Previously, when encountering an item that would not fit in the current box under evaluation, the algorithm would declare the box full and open a new one. Now it will continue to pack any remaining smaller items into the current box before moving on.
### Fixed
 - Boxes and items with large volumes were sometimes not sorted properly because of issues with integer overflow inside SplMinHeap. This could lead to suboptimal results. [IBBoard]

## [1.4.2] - 2014-11-19
### Fixed
 - In some cases, items that only fit in a single orientation were being recorded as fitting in the alternate, impossible one [TravisBernard]

## [1.4.1] - 2014-08-13
### Fixed
 - Fixed infinite loop that could occur in certain circumstances

## [1.4] - 2014-08-10
### Changed
 - Better stacking/depth calculations

## [1.3] - 2014-07-23
### Fixed
 - Fixed problem where available space calculation inadvertently missed off a dimension

## [1.2] - 2014-05-31
### Added
 - Expose remaining space information on a packed box
### Fixed
 - Fixed bug that preferred less-optimal solutions in some cases

## [1.1] - 2014-03-30
### Added
 - Support for HHVM
### Changed
 - Tweaked algorithm to allow limited lookahead when dealing with identical objects to better optimise placement
 - Misc internal refactoring and optimisations

## [1.0.1] - 2014-01-23
### Fixed
 - Fixed issue with vertical depth calculation where last item in a box could be judged not to fit even though it would

## [1.0] - 2013-11-28
### Added
 - Generated solutions now have a second pass where multiple boxes are involved, in order to redistribute weight more evenly
### Removed
 - PHP 5.3 support

## [0.4] - 2013-08-11
### Changed
 - Minor calculation speedups

## [0.3] - 2013-08-10
### Changed
 - Now packs items side by side, not just on top of each other
 - Generated solutions should now be reasonably optimal

## [0.2] - 2013-08-01
### Added
 - Supports solutions using multiple boxes
### Changed
 - API should be stable now - no plans to change it
 - Generated solutions may not be optimal, but should be correct

## 0.1 - 2013-08-01
Initial release
### Added
 - Basic prototype
 - Experimental code to get a feel for how calculations can best be implemented
 - Only works if all items fit into a single box (so not production ready at all)

[Unreleased]: https://github.com/dvdoug/BoxPacker/compare/3.5.2...master

[3.5.2]: https://github.com/dvdoug/BoxPacker/compare/3.5.1...3.5.2
[3.5.1]: https://github.com/dvdoug/BoxPacker/compare/3.5.0...3.5.1
[3.5.0]: https://github.com/dvdoug/BoxPacker/compare/3.4.1...3.5.0
[3.4.1]: https://github.com/dvdoug/BoxPacker/compare/3.4.0...3.4.1
[3.4.0]: https://github.com/dvdoug/BoxPacker/compare/3.3.0...3.4.0
[3.3.0]: https://github.com/dvdoug/BoxPacker/compare/3.2.2...3.3.0
[3.2.2]: https://github.com/dvdoug/BoxPacker/compare/3.2.1...3.2.2
[3.2.1]: https://github.com/dvdoug/BoxPacker/compare/3.2.0...3.2.1
[3.2.0]: https://github.com/dvdoug/BoxPacker/compare/3.1.3...3.2.0
[3.1.3]: https://github.com/dvdoug/BoxPacker/compare/3.1.2...3.1.3
[3.1.2]: https://github.com/dvdoug/BoxPacker/compare/3.1.1...3.1.2
[3.1.1]: https://github.com/dvdoug/BoxPacker/compare/3.1.0...3.1.1
[3.1.0]: https://github.com/dvdoug/BoxPacker/compare/3.0.1...3.1.0
[3.0.1]: https://github.com/dvdoug/BoxPacker/compare/3.0.0...3.0.1
[3.0.0]: https://github.com/dvdoug/BoxPacker/compare/2.4.2...3.0.0
[2.6.5]: https://github.com/dvdoug/BoxPacker/compare/2.6.4...2.6.5
[2.6.4]: https://github.com/dvdoug/BoxPacker/compare/2.6.3...2.6.4
[2.6.3]: https://github.com/dvdoug/BoxPacker/compare/2.6.2...2.6.3
[2.6.2]: https://github.com/dvdoug/BoxPacker/compare/2.6.1...2.6.2
[2.6.1]: https://github.com/dvdoug/BoxPacker/compare/2.6.0...2.6.1
[2.6.0]: https://github.com/dvdoug/BoxPacker/compare/2.5.0...2.6.0
[2.5.0]: https://github.com/dvdoug/BoxPacker/compare/2.4.8...2.5.0
[2.4.8]: https://github.com/dvdoug/BoxPacker/compare/2.4.7...2.4.8
[2.4.7]: https://github.com/dvdoug/BoxPacker/compare/2.4.6...2.4.7
[2.4.6]: https://github.com/dvdoug/BoxPacker/compare/2.4.5...2.4.6
[2.4.5]: https://github.com/dvdoug/BoxPacker/compare/2.4.4...2.4.5
[2.4.4]: https://github.com/dvdoug/BoxPacker/compare/2.4.3...2.4.4
[2.4.3]: https://github.com/dvdoug/BoxPacker/compare/2.4.2...2.4.3
[2.4.2]: https://github.com/dvdoug/BoxPacker/compare/2.4.1...2.4.2
[2.4.1]: https://github.com/dvdoug/BoxPacker/compare/2.4.0...2.4.1
[2.4.0]: https://github.com/dvdoug/BoxPacker/compare/2.3.2...2.4.0
[2.3.2]: https://github.com/dvdoug/BoxPacker/compare/2.3.1...2.3.2
[2.3.1]: https://github.com/dvdoug/BoxPacker/compare/2.3.0...2.3.1
[2.3.0]: https://github.com/dvdoug/BoxPacker/compare/2.2.1...2.3.0
[2.2.1]: https://github.com/dvdoug/BoxPacker/compare/2.2.0...2.2.1
[2.2.0]: https://github.com/dvdoug/BoxPacker/compare/2.1.0...2.2.0
[2.1.0]: https://github.com/dvdoug/BoxPacker/compare/2.0.2...2.1.0
[2.0.2]: https://github.com/dvdoug/BoxPacker/compare/2.0.1...2.0.2
[2.0.1]: https://github.com/dvdoug/BoxPacker/compare/2.0...2.0.1
[2.0]: https://github.com/dvdoug/BoxPacker/compare/1.5.3...2.0
[1.7.2]: https://github.com/dvdoug/BoxPacker/compare/1.7.1...1.7.2
[1.7.1]: https://github.com/dvdoug/BoxPacker/compare/1.7.0...1.7.1
[1.7.0]: https://github.com/dvdoug/BoxPacker/compare/1.6.9...1.7.0
[1.6.9]: https://github.com/dvdoug/BoxPacker/compare/1.6.8...1.6.9
[1.6.8]: https://github.com/dvdoug/BoxPacker/compare/1.6.7...1.6.8
[1.6.7]: https://github.com/dvdoug/BoxPacker/compare/1.6.6...1.6.7
[1.6.6]: https://github.com/dvdoug/BoxPacker/compare/1.6.5...1.6.6
[1.6.5]: https://github.com/dvdoug/BoxPacker/compare/1.6.4...1.6.5
[1.6.4]: https://github.com/dvdoug/BoxPacker/compare/1.6.3...1.6.4
[1.6.3]: https://github.com/dvdoug/BoxPacker/compare/1.6.2...1.6.3
[1.6.2]: https://github.com/dvdoug/BoxPacker/compare/1.6.1...1.6.2
[1.6.1]: https://github.com/dvdoug/BoxPacker/compare/1.6.0...1.6.1
[1.6.0]: https://github.com/dvdoug/BoxPacker/compare/1.5.3...1.6.0
[1.5.3]: https://github.com/dvdoug/BoxPacker/compare/1.5.2...1.5.3
[1.5.2]: https://github.com/dvdoug/BoxPacker/compare/1.5.1...1.5.2
[1.5.1]: https://github.com/dvdoug/BoxPacker/compare/1.5...1.5.1
[1.5]: https://github.com/dvdoug/BoxPacker/compare/1.4.2...1.5
[1.4.2]: https://github.com/dvdoug/BoxPacker/compare/1.4.1...1.4.2
[1.4.1]: https://github.com/dvdoug/BoxPacker/compare/1.4...1.4.1
[1.4]: https://github.com/dvdoug/BoxPacker/compare/1.3...1.4
[1.3]: https://github.com/dvdoug/BoxPacker/compare/1.2...1.3
[1.2]: https://github.com/dvdoug/BoxPacker/compare/1.1...1.2
[1.1]: https://github.com/dvdoug/BoxPacker/compare/1.0.1...1.1
[1.0.1]: https://github.com/dvdoug/BoxPacker/compare/1.0...1.0.1
[1.0]: https://github.com/dvdoug/BoxPacker/compare/0.4...1.0
[0.4]: https://github.com/dvdoug/BoxPacker/compare/0.3...0.4
[0.3]: https://github.com/dvdoug/BoxPacker/compare/0.2...0.3
[0.2]: https://github.com/dvdoug/BoxPacker/compare/0.1...0.2
