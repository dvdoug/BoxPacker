<?php

use Behat\Behat\Context\Context;
use DVDoug\BoxPacker\Box;
use DVDoug\BoxPacker\BoxList;
use DVDoug\BoxPacker\Item;
use DVDoug\BoxPacker\ItemList;
use DVDoug\BoxPacker\PackedBox;
use DVDoug\BoxPacker\PackedBoxList;
use DVDoug\BoxPacker\Packer;
use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;
use DVDoug\BoxPacker\VolumePacker;
use PHPUnit\Framework\Assert;

/**
 * Defines application features from the specific context.
 */
class PackerContext implements Context
{
    /** @var Box */
    protected $box;

    /** @var BoxList */
    protected $boxList;

    /** @var ItemList */
    protected $itemList;

    /** @var PackedBox */
    protected $packedBox;

    /** @var PackedBoxList */
    protected $packedBoxList;

    /** @var string */
    protected $packerClass = 'DVDoug\BoxPacker\Packer';

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
        $this->boxList = new BoxList();
        $this->itemList = new ItemList();
    }

    /**
     * @Given /^there is a box "([^"]+)", which has external dimensions (\d+)mm w × (\d+)mm l × (\d+)mm d × (\d+)g and internal dimensions (\d+)mm w × (\d+)mm l × (\d+)mm d and has a max weight of (\d+)g$/
     */
    public function thereIsABox(
        $boxType,
        $outerWidth,
        $outerLength,
        $outerDepth,
        $emptyWeight,
        $innerWidth,
        $innerLength,
        $innerDepth,
        $maxWeight
    ) {
        $box = new TestBox($boxType, $outerWidth, $outerLength, $outerDepth, $emptyWeight, $innerWidth, $innerLength, $innerDepth, $maxWeight);
        $this->boxList->insert($box);
    }

    /**
     * @Given /^the box "([^"]+)", which has external dimensions (\d+)mm w × (\d+)mm l × (\d+)mm d × (\d+)g and internal dimensions (\d+)mm w × (\d+)mm l × (\d+)mm d and has a max weight of (\d+)g$/
     */
    public function theBox(
        $boxType,
        $outerWidth,
        $outerLength,
        $outerDepth,
        $emptyWeight,
        $innerWidth,
        $innerLength,
        $innerDepth,
        $maxWeight
    ) {
        $box = new TestBox($boxType, $outerWidth, $outerLength, $outerDepth, $emptyWeight, $innerWidth, $innerLength, $innerDepth, $maxWeight);
        $this->box = $box;
    }

    /**
     * @When /^I add (\d+) x "([^"]+)" with dimensions (\d+)mm w × (\d+)mm l × (\d+)mm d × (\d+)g$/
     */
    public function thereIsAnItem(
        $qty,
        $itemName,
        $width,
        $length,
        $depth,
        $weight
    ) {
        $item = new TestItem($itemName, $width, $length, $depth, $weight, false);
        for ($i = 0; $i < $qty; $i++) {
            $this->itemList->insert($item);
        }
    }

    /**
     * @When /^I add (\d+) x keep flat "([^"]+)" with dimensions (\d+)mm w × (\d+)mm l × (\d+)mm d × (\d+)g$/
     */
    public function thereIsAKeepFlatItem(
        $qty,
        $itemName,
        $width,
        $length,
        $depth,
        $weight
    ) {
        $item = new TestItem($itemName, $width, $length, $depth, $weight, true);
        for ($i = 0; $i < $qty; $i++) {
            $this->itemList->insert($item);
        }
    }

    /**
     * @When I do a packing
     */
    public function iDoAPacking()
    {
        /** @var Packer $packer */
        $packer = new $this->packerClass();
        $packer->setBoxes($this->boxList);
        $packer->setItems($this->itemList);
        $this->packedBoxList = $packer->pack();
    }

    /**
     * @When I do a volume-only packing
     */
    public function iDoAVolumePacking()
    {
        $packer = new VolumePacker($this->box, $this->itemList);
        $this->packedBox = $packer->pack();
    }

    /**
     * @Then /^I should have (\d+) boxes of type "([^"]+)"$/
     */
    public function thereExistsBoxes(
        $qty,
        $boxType
    ) {
        $foundBoxes = 0;

        /** @var PackedBox $packedBox */
        foreach (clone $this->packedBoxList as $packedBox) {
            if ($packedBox->getBox()->getReference() === $boxType) {
                $foundBoxes++;
            }
        }

        Assert::assertEquals($qty, $foundBoxes);
    }

    /**
     * @Then /^the packed box should have (\d+) items of type "([^"]+)"$/
     */
    public function thePackedBoxShouldHaveItems(
        $qty,
        $itemType
    ) {
        $foundItems = 0;

        /** @var Item $packedItem */
        foreach (clone $this->packedBox->getItems() as $packedItem) {
            if ($packedItem->getDescription() === $itemType) {
                $foundItems++;
            }
        }

        Assert::assertEquals($qty, $foundItems);
    }
}
