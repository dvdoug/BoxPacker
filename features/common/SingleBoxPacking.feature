Feature: SingleBoxPacking
  In order to make logistics easier
  As a developer
  I need to be able to find out how a set of items can fit into a box

  Scenario: Packing 3 items that fit easily
    Given the box "Box", which has external dimensions 300mm w × 300mm l × 10mm d × 10g and internal dimensions 296mm w × 296mm l × 8mm d and has a max weight of 1000g
    When I add 3 x "Small Item" with dimensions 250mm w × 250mm l × 2mm d × 200g
    And I do a volume-only packing
    Then the packed box should have 3 items of type "Small Item"

  Scenario: Packing 3 items that fit exactly
    Given the box "Box", which has external dimensions 300mm w × 300mm l × 10mm d × 10g and internal dimensions 296mm w × 296mm l × 8mm d and has a max weight of 1000g
    When I add 2 x "Small Item" with dimensions 250mm w × 250mm l × 2mm d × 200g
    When I add 1 x "Medium Item" with dimensions 250mm w × 250mm l × 4mm d × 200g
    And I do a volume-only packing
    Then the packed box should have 2 items of type "Small Item"
    And the packed box should have 1 items of type "Medium Item"

  Scenario: Packing 3 items that would fit exactly but break the weight limit
    Given the box "Box", which has external dimensions 300mm w × 300mm l × 10mm d × 10g and internal dimensions 296mm w × 296mm l × 8mm d and has a max weight of 1000g
    When I add 1 x "Small Item" with dimensions 250mm w × 250mm l × 2mm d × 200g
    When I add 1 x "Medium Item" with dimensions 250mm w × 250mm l × 2mm d × 400g
    When I add 1 x "Heavy Item" with dimensions 250mm w × 250mm l × 2mm d × 500g
    And I do a volume-only packing
    Then the packed box should have 0 items of type "Small Item"
    And the packed box should have 1 items of type "Medium Item"
    And the packed box should have 1 items of type "Heavy Item"

  Scenario: Packing 2 items that fit exactly as long as they aren't rotated
    Given the box "Box", which has external dimensions 300mm w × 300mm l × 10mm d × 10g and internal dimensions 296mm w × 296mm l × 8mm d and has a max weight of 1000g
    When I add 1 x "Small Item" with dimensions 296mm w × 148mm l × 2mm d × 200g
    When I add 1 x "Medium Item" with dimensions 296mm w × 148mm l × 4mm d × 500g
    And I do a volume-only packing
    Then the packed box should have 1 items of type "Small Item"
    And the packed box should have 1 items of type "Medium Item"

  Scenario: Packing 3 items where 2 are oversized
    Given the box "Box", which has external dimensions 300mm w × 300mm l × 10mm d × 10g and internal dimensions 296mm w × 296mm l × 8mm d and has a max weight of 1000g
    When I add 1 x "Item A" with dimensions 297mm w × 296mm l × 2mm d × 200g
    When I add 1 x "Item B" with dimensions 297mm w × 296mm l × 2mm d × 500g
    When I add 1 x "Item C" with dimensions 296mm w × 296mm l × 4mm d × 290g
    And I do a volume-only packing
    Then the packed box should have 0 items of type "Item A"
    And the packed box should have 0 items of type "Item B"
    And the packed box should have 1 items of type "Item C"

  Scenario: Packing 2 items that fit exactly side by side after rotating
    Given the box "Box", which has external dimensions 300mm w × 500mm l × 10mm d × 10g and internal dimensions 296mm w × 496mm l × 8mm d and has a max weight of 1000g
    When I add 1 x "Item A" with dimensions 296mm w × 248mm l × 8mm d × 200g
    When I add 1 x "Item B" with dimensions 248mm w × 296mm l × 8mm d × 200g
    And I do a volume-only packing
    Then the packed box should have 1 items of type "Item A"
    And the packed box should have 1 items of type "Item B"

  Scenario: Packing 3 items with 2 fitting exactly side by side after rotating, and then 1 stacked (perfect fit)
    Given the box "Box", which has external dimensions 300mm w × 300mm l × 10mm d × 10g and internal dimensions 296mm w × 296mm l × 8mm d and has a max weight of 1000g
    When I add 1 x "Item A" with dimensions 248mm w × 148mm l × 4mm d × 200g
    When I add 1 x "Item B" with dimensions 148mm w × 248mm l × 4mm d × 200g
    When I add 1 x "Item C" with dimensions 296mm w × 296mm l × 4mm d × 200g
    And I do a volume-only packing
    Then the packed box should have 1 items of type "Item A"
    And the packed box should have 1 items of type "Item B"
    And the packed box should have 1 items of type "Item C"

  Scenario: Packing 3 items with 2 fitting exactly side by side after rotating, and then 1 stacked (with overhang)
    Given the box "Box", which has external dimensions 250mm w × 250mm l × 10mm d × 10g and internal dimensions 248mm w × 248mm l × 8mm d and has a max weight of 1000g
    When I add 1 x "Item A" with dimensions 200mm w × 200mm l × 4mm d × 200g
    When I add 2 x "Item B" with dimensions 110mm w × 110mm l × 4mm d × 200g
    And I do a volume-only packing
    Then the packed box should have 1 items of type "Item A"
    And the packed box should have 2 items of type "Item B"

  Scenario: Packing an item which requires 3D rotation to fit
    Given the box "Box", which has external dimensions 100mm w × 100mm l × 300mm d × 10g and internal dimensions 100mm w × 100mm l × 300mm d and has a max weight of 1500g
    When I add 1 x "Item A" with dimensions 150mm w × 50mm l × 50mm d × 20g
    And I do a volume-only packing
    Then the packed box should have 1 items of type "Item A"

  Scenario: Not 3D rotating unnecessarily (issue #53)
    Given the box "Box", which has external dimensions 500mm w × 1000mm l × 500mm d × 0g and internal dimensions 500mm w × 1000mm l × 500mm d and has a max weight of 0g
    When I add 1 x "Item A" with dimensions 500mm w × 500mm l × 500mm d × 0g
    When I add 2 x "Item B" with dimensions 500mm w × 500mm l × 250mm d × 0g
    And I do a volume-only packing
    Then the packed box should have 1 items of type "Item A"
    Then the packed box should have 2 items of type "Item B"

  Scenario: Making sure whatever bug caused issue #75 doesn't come back
    Given the box "Box", which has external dimensions 20mm w × 12mm l × 10mm d × 0g and internal dimensions 20mm w × 12mm l × 10mm d and has a max weight of 2500g
    When I add 2 x "Item A" with dimensions 12mm w × 12mm l × 5mm d × 8g
    When I add 2 x "Item B" with dimensions 8mm w × 12mm l × 5mm d × 8g
    And I do a volume-only packing
    Then the packed box should have 2 items of type "Item A"
    Then the packed box should have 2 items of type "Item B"

  Scenario: Test identical items that could be fit side by side actually do (issue #89)
    Given the box "SRA3 Sheet", which has external dimensions 450mm w × 320mm l × 1mm d × 0g and internal dimensions 450mm w × 320mm l × 1mm d and has a max weight of 0g
    When I add 4 x "A5 Sheet" with dimensions 148mm w × 210mm l × 1mm d × 0g
    And I do a volume-only packing
    Then the packed box should have 4 items of type "A5 Sheet"

  Scenario: Test smaller cubes successfully fit into a larger cube (issue #9)
    Given the box "Box", which has external dimensions 24mm w × 24mm l × 24mm d × 24g and internal dimensions 24mm w × 24mm l × 24mm d and has a max weight of 100g
    When I add 64 x keep flat "Item" with dimensions 6mm w × 6mm l × 6mm d × 1g
    And I do a volume-only packing
    Then the packed box should have 64 items of type "Item"

  Scenario: Test shallower items can be stacked alongside a taller one (issue #11)
    Given the box "Box", which has external dimensions 4mm w × 4mm l × 4mm d × 4g and internal dimensions 4mm w × 4mm l × 4mm d and has a max weight of 100g
    When I add 2 x keep flat "Tall Item" with dimensions 2mm w × 2mm l × 4mm d × 1g
    When I add 32 x keep flat "Shallow Item" with dimensions 1mm w × 1mm l × 1mm d × 1g
    And I do a volume-only packing
    Then the packed box should have 2 items of type "Tall Item"
    And the packed box should have 32 items of type "Shallow Item"

  Scenario: Test shallower items can be stacked alongside a taller one (issue #13)
    Given the box "Box", which has external dimensions 12mm w × 12mm l × 12mm d × 12g and internal dimensions 10mm w × 10mm l × 10mm d and has a max weight of 1000g
    When I add 2 x keep flat "Item A" with dimensions 5mm w × 3mm l × 2mm d × 2g
    When I add 1 x keep flat "Item B" with dimensions 3mm w × 3mm l × 3mm d × 3g
    And I do a volume-only packing
    Then the packed box should have 2 items of type "Item A"
    And the packed box should have 1 items of type "Item B"
