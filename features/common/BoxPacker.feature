Feature: BoxPacker
  In order to make logistics easier
  As a developer
  I need to be able to find out how a set of items can fit into a set of boxes

  Scenario: Fitting small items into a small box only
    Given there is a box "Small", which has external dimensions 300mm w × 300mm l × 10mm d × 10g and internal dimensions 296mm w × 296mm l × 8mm d and has a max weight of 1000g
    And there is a box "Large", which has external dimensions 3000mm w × 3000mm l × 100mm d × 100g and internal dimensions 2960mm w × 2960mm l × 80mm d and has a max weight of 10000g
    When I add 3 x keep flat "Small Item" with dimensions 250mm w × 250mm l × 2mm d × 200g
    And I do a packing
    Then I should have 1 boxes of type "Small"
    And I should have 0 boxes of type "Large"

  Scenario: Fitting large items into a large box only
    Given there is a box "Small", which has external dimensions 300mm w × 300mm l × 10mm d × 10g and internal dimensions 296mm w × 296mm l × 8mm d and has a max weight of 1000g
    And there is a box "Large", which has external dimensions 3000mm w × 3000mm l × 100mm d × 100g and internal dimensions 2960mm w × 2960mm l × 80mm d and has a max weight of 10000g
    When I add 3 x keep flat "Large Item" with dimensions 2500mm w × 2500mm l × 20mm d × 2000g
    And I do a packing
    Then I should have 0 boxes of type "Small"
    And I should have 1 boxes of type "Large"

  Scenario: Fitting mixed size items into a mix of box sizes
    Given there is a box "Small", which has external dimensions 600mm w × 600mm l × 10mm d × 10g and internal dimensions 596mm w × 596mm l × 8mm d and has a max weight of 1000g
    And there is a box "Large", which has external dimensions 3000mm w × 3000mm l × 50mm d × 100g and internal dimensions 2960mm w × 2960mm l × 40mm d and has a max weight of 10000g
    When I add 1 x keep flat "Small Item" with dimensions 550mm w × 550mm l × 2mm d × 500g
    When I add 4 x keep flat "Large Item" with dimensions 2500mm w × 2500mm l × 20mm d × 500g
    And I do a packing
    Then I should have 1 boxes of type "Small"
    And I should have 2 boxes of type "Large"

  Scenario: Simple stacking
    Given there is a box "Small", which has external dimensions 292mm w × 336mm l × 60mm d × 10g and internal dimensions 292mm w × 336mm l × 60mm d and has a max weight of 9000g
    And there is a box "Large", which has external dimensions 421mm w × 548mm l × 335mm d × 100g and internal dimensions 421mm w × 548mm l × 335mm d and has a max weight of 10000g
    When I add 1 x keep flat "Small Item" with dimensions 226mm w × 200mm l × 40mm d × 440g
    When I add 1 x keep flat "Large Item" with dimensions 200mm w × 200mm l × 155mm d × 1660g
    And I do a packing
    Then I should have 0 boxes of type "Small"
    And I should have 1 boxes of type "Large"

  Scenario: Making sure whatever bug caused issue #3 doesn't come back
    Given there is a box "Box A", which has external dimensions 51mm w × 33mm l × 33mm d × 1g and internal dimensions 51mm w × 33mm l × 33mm d and has a max weight of 1g
    And there is a box "Box B", which has external dimensions 50mm w × 40mm l × 40mm d × 1g and internal dimensions 50mm w × 40mm l × 40mm d and has a max weight of 1g
    When I add 6 x keep flat "Item" with dimensions 28mm w × 19mm l × 9mm d × 0g
    And I do a packing
    Then I should have 1 boxes of type "Box A"
    And I should have 0 boxes of type "Box B"
