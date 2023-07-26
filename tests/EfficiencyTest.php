<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\TestCase;

use function fclose;
use function fgetcsv;
use function fopen;

/**
 * @coversNothing
 */
class EfficiencyTest extends TestCase
{
    /**
     * @dataProvider getSamples
     * @group efficiency
     */
    public function testCanPackRepresentativeLargerSamples(
        array $boxes,
        array $items,
        int $expectedBoxes2D,
        int $expectedBoxes3D,
        float $expectedWeightVariance2D,
        float $expectedWeightVariance3D,
        float $expectedVolumeUtilisation2D,
        float $expectedVolumeUtilisation3D
    ): void {
        $expectedItemCount = 0;

        $packer2D = new Packer();
        $packer3D = new Packer();

        foreach ($boxes as $box) {
            $packer2D->addBox($box);
            $packer3D->addBox($box);
        }
        foreach ($items as $item) {
            $expectedItemCount += $item['qty'];

            $packer2D->addItem(
                new TestItem(
                    $item['name'],
                    $item['width'],
                    $item['length'],
                    $item['depth'],
                    $item['weight'],
                    Rotation::KeepFlat
                ),
                (int) $item['qty']
            );

            $packer3D->addItem(
                new TestItem(
                    $item['name'],
                    $item['width'],
                    $item['length'],
                    $item['depth'],
                    $item['weight'],
                    Rotation::BestFit
                ),
                (int) $item['qty']
            );
        }
        $packedBoxes2D = $packer2D->pack();
        $packedBoxes3D = $packer3D->pack();

        $packedItemCount2D = 0;
        foreach ($packedBoxes2D as $packedBox) {
            $packedItemCount2D += $packedBox->getItems()->count();
        }

        $packedItemCount3D = 0;
        foreach ($packedBoxes3D as $packedBox) {
            $packedItemCount3D += $packedBox->getItems()->count();
        }

        self::assertCount($expectedBoxes2D, $packedBoxes2D);
        self::assertEquals($expectedItemCount, $packedItemCount2D);
        self::assertEquals($expectedVolumeUtilisation2D, $packedBoxes2D->getVolumeUtilisation());
        self::assertEquals($expectedWeightVariance2D, $packedBoxes2D->getWeightVariance());

        self::assertCount($expectedBoxes3D, $packedBoxes3D);
        self::assertEquals($expectedItemCount, $packedItemCount3D);
        self::assertEquals($expectedVolumeUtilisation3D, $packedBoxes3D->getVolumeUtilisation());
        self::assertEquals($expectedWeightVariance3D, $packedBoxes3D->getWeightVariance());
    }

    public static function getSamples(): array
    {
        $expected = ['2D' => [], '3D' => []];

        $expected2DData = fopen(__DIR__ . '/data/expected.csv', 'rb');
        while ($data = fgetcsv($expected2DData)) {
            $expected['2D'][$data[0]] = ['boxes' => $data[1], 'weightVariance' => $data[2], 'utilisation' => $data[3]];
            $expected['3D'][$data[0]] = ['boxes' => $data[4], 'weightVariance' => $data[5], 'utilisation' => $data[6]];
        }
        fclose($expected2DData);

        $boxes = [];
        $boxData = fopen(__DIR__ . '/data/boxes.csv', 'rb');
        while ($data = fgetcsv($boxData)) {
            $boxes[] = new TestBox(
                $data[0],
                (int) $data[1],
                (int) $data[2],
                (int) $data[3],
                (int) $data[4],
                (int) $data[5],
                (int) $data[6],
                (int) $data[7],
                (int) $data[8]
            );
        }
        fclose($boxData);

        $tests = [];
        $itemData = fopen(__DIR__ . '/data/items.csv', 'rb');
        while ($data = fgetcsv($itemData)) {
            if (isset($tests[$data[0]])) {
                $tests[$data[0]]['items'][] = [
                    'qty' => (int) $data[1],
                    'name' => $data[2],
                    'width' => (int) $data[3],
                    'length' => (int) $data[4],
                    'depth' => (int) $data[5],
                    'weight' => (int) $data[6],
                ];
            } else {
                $tests[$data[0]] = [
                    'boxes' => $boxes,
                    'items' => [
                        [
                            'qty' => (int) $data[1],
                            'name' => $data[2],
                            'width' => (int) $data[3],
                            'length' => (int) $data[4],
                            'depth' => (int) $data[5],
                            'weight' => (int) $data[6],
                        ],
                    ],
                    'expected2D' => (int) $expected['2D'][$data[0]]['boxes'],
                    'expected3D' => (int) $expected['3D'][$data[0]]['boxes'],
                    'weightVariance2D' => (float) $expected['2D'][$data[0]]['weightVariance'],
                    'weightVariance3D' => (float) $expected['3D'][$data[0]]['weightVariance'],
                    'volumeUtilisation2D' => (float) $expected['2D'][$data[0]]['utilisation'],
                    'volumeUtilisation3D' => (float) $expected['3D'][$data[0]]['utilisation'],
                ];
            }
        }
        fclose($itemData);

        return $tests;
    }
}
