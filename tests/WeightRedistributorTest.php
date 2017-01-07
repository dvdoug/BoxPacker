<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */

namespace DVDoug\BoxPacker;

class WeightRedistributorTest extends \PHPUnit_Framework_TestCase
{
    //PHP7/HHVM behave(d) differently than PHP5.x
    public function testWeightRedistribution()
    {

        $box = new TestBox('Box', 370, 375, 60, 140, 364, 374, 40, 3000);
        $boxList = new BoxList();
        $boxList->insert($box);

        $item1 = new TestItem('Item #1', 230, 330, 6, 320, true);
        $item2 = new TestItem('Item #2', 210, 297, 5, 187, true);
        $item3 = new TestItem('Item #3', 210, 297, 11, 674, true);
        $item4 = new TestItem('Item #4', 210, 297, 3, 82, true);
        $item5 = new TestItem('Item #5', 206, 295, 4, 217, true);

        $box1Items = new ItemList();
        $box1Items->insert(clone $item1);
        $box1Items->insert(clone $item1);
        $box1Items->insert(clone $item1);
        $box1Items->insert(clone $item1);
        $box1Items->insert(clone $item1);
        $box1Items->insert(clone $item1);
        $box1Items->insert(clone $item5);

        $box2Items = new ItemList();
        $box2Items->insert(clone $item3);
        $box2Items->insert(clone $item1);
        $box2Items->insert(clone $item1);
        $box2Items->insert(clone $item1);
        $box2Items->insert(clone $item1);
        $box2Items->insert(clone $item2);

        $box3Items = new ItemList();
        $box3Items->insert(clone $item5);
        $box3Items->insert(clone $item4);

        $packedBox1 = new PackedBox($box, $box1Items, 0, 0, 0, 0, 0, 0, 0);
        $packedBox2 = new PackedBox($box, $box2Items, 0, 0, 0, 0, 0, 0, 0);
        $packedBox3 = new PackedBox($box, $box3Items, 0, 0, 0, 0, 0, 0, 0);

        $packedBoxList = new PackedBoxList();
        $packedBoxList->insert($packedBox1);
        $packedBoxList->insert($packedBox2);
        $packedBoxList->insert($packedBox3);

        $redistributor = new WeightRedistributor($boxList);
        $packedBoxes = $redistributor->redistributeWeight($packedBoxList);

        $packedItemCount = 0;
        foreach (clone $packedBoxes as $packedBox) {
            $packedItemCount += $packedBox->getItems()->count();
        }

        self::assertEquals(3070, (int)$packedBoxes->getWeightVariance());
    }
}
