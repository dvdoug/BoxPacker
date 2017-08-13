# Changelog

## [Unreleased]
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

[Unreleased]: https://github.com/dvdoug/BoxPacker/compare/2.3.2...master

[2.3.2]: https://github.com/dvdoug/BoxPacker/compare/2.3.1...2.3.2
[2.3.1]: https://github.com/dvdoug/BoxPacker/compare/2.3.0...2.3.1
[2.3.0]: https://github.com/dvdoug/BoxPacker/compare/2.2.1...2.3.0
[2.2.1]: https://github.com/dvdoug/BoxPacker/compare/2.2.0...2.2.1
[2.2.0]: https://github.com/dvdoug/BoxPacker/compare/2.1.0...2.2.0
[2.1.0]: https://github.com/dvdoug/BoxPacker/compare/2.0.2...2.1.0
[2.0.2]: https://github.com/dvdoug/BoxPacker/compare/2.0.1...2.0.2
[2.0.1]: https://github.com/dvdoug/BoxPacker/compare/2.0...2.0.1
[2.0]: https://github.com/dvdoug/BoxPacker/compare/1.5.3...2.0
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
