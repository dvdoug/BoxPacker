Feature: BoxPacker
  In order to make logistics easier
  As a user
  I need to be able to ask items to be packed that don't fit into any available box

  Scenario: Fitting 2 item that fits and 3 that don't into a box
    Given there is a box "Small", which has external dimensions 300mm w × 300mm l × 10mm d × 10g and internal dimensions 296mm w × 296mm l × 8mm d and has a max weight of 1000g
    When I add 2 x "Small Item" with dimensions 250mm w × 250mm l × 2mm d × 200g
    When I add 3 x "Large Item" with dimensions 350mm w × 250mm l × 2mm d × 200g
    And I do an infallible packing
    Then I should have 1 boxes of type "Small"
    And the unpacked item list should have 3 items of type "Large Item"

  Scenario: Fitting 0 item that fits and 3 that don't into a box
    Given there is a box "Small", which has external dimensions 300mm w × 300mm l × 10mm d × 10g and internal dimensions 296mm w × 296mm l × 8mm d and has a max weight of 1000g
    When I add 3 x "Large Item" with dimensions 350mm w × 250mm l × 2mm d × 200g
    And I do an infallible packing
    Then I should have 0 boxes of type "Small"
    And the unpacked item list should have 3 items of type "Large Item"
