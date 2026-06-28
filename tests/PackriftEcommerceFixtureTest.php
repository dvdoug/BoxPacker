<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Exception\NoBoxesAvailableException;
use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

#[CoversNothing]
class PackriftEcommerceFixtureTest extends TestCase
{
    private const MAX_WEIGHT = 50000;

    /**
     * Static ecommerce carton sample from Packrift's public packaging optimization corpus:
     * https://github.com/Packrift/packaging-optimization-benchmark-corpus/blob/main/examples/ortools-carton-selection/sample_cartons.csv
     * Catalog inch dimensions are rounded to integer millimetres for this test fixture.
     */
    private const PACKRIFT_CARTONS = [
        [
            'sku' => '1066',
            'title' => '10x6x6 ECT-32 Kraft Long Corrugated Boxes - 25 Bundle',
            'url' => 'https://packrift.com/products/10x6x6-ect-32-kraft-long-corrugated-boxes-25-bundle',
            'width' => 152,
            'length' => 254,
            'depth' => 152,
        ],
        [
            'sku' => '1684',
            'title' => '16x8x4 ECT-32 Kraft Corrugated Shipping Boxes - 25 Bundle',
            'url' => 'https://packrift.com/products/16x8x4-ect-32-kraft-corrugated-shipping-boxes-25-bundle',
            'width' => 203,
            'length' => 406,
            'depth' => 102,
        ],
        [
            'sku' => '20146',
            'title' => '20x14x6 ECT-32 Kraft Corrugated Boxes - Shallow Depth Design, Bundle of 25',
            'url' => 'https://packrift.com/products/20x14x6-ect-32-kraft-corrugated-boxes-shallow-depth-design-bundle-of-25',
            'width' => 356,
            'length' => 508,
            'depth' => 152,
        ],
        [
            'sku' => '24108',
            'title' => '24x10x8 ECT-32 Kraft Corrugated Long Boxes - Easy-Pack Design, Bundle of 25',
            'url' => 'https://packrift.com/products/24x10x8-ect-32-kraft-corrugated-long-boxes-easy-pack-design-bundle-of-25',
            'width' => 254,
            'length' => 610,
            'depth' => 203,
        ],
        [
            'sku' => '402020',
            'title' => '40x20x20 ECT-32 Kraft Corrugated Boxes - Bulk Shipping, 10 Pack',
            'url' => 'https://packrift.com/products/40x20x20-ect-32-kraft-corrugated-boxes-bulk-shipping-10-pack',
            'width' => 508,
            'length' => 1016,
            'depth' => 508,
        ],
    ];

    #[DataProvider('packableEcommerceOrders')]
    public function testCanPackEcommerceOrdersUsingPackriftSampleCartons(
        string $expectedSku,
        array $items
    ): void {
        $packer = new Packer();
        $this->addPackriftSampleCartons($packer);

        $expectedItemCount = 0;
        foreach ($items as $item) {
            $expectedItemCount += $item['qty'];
            $packer->addItem(
                new TestItem(
                    $item['description'],
                    $item['width'],
                    $item['length'],
                    $item['depth'],
                    $item['weight'],
                    Rotation::BestFit
                ),
                $item['qty']
            );
        }

        $packedBoxes = iterator_to_array($packer->pack(), false);

        self::assertCount(1, $packedBoxes);
        self::assertCount($expectedItemCount, $packedBoxes[0]->items);
        self::assertSame("Packrift {$expectedSku}", $packedBoxes[0]->box->getReference());
    }

    public function testThrowsWhenOrderDoesNotFitPackriftSampleCartons(): void
    {
        $this->expectException(NoBoxesAvailableException::class);

        $packer = new Packer();
        $this->addPackriftSampleCartons($packer);
        $packer->addItem(new TestItem('oversized equipment case', 1200, 600, 600, 15000, Rotation::BestFit));

        $packer->pack();
    }

    public static function packableEcommerceOrders(): array
    {
        return [
            'small accessory order' => [
                '1066',
                [
                    [
                        'qty' => 1,
                        'description' => 'small boxed accessory',
                        'width' => 220,
                        'length' => 120,
                        'depth' => 90,
                        'weight' => 300,
                    ],
                ],
            ],
            'shallow kit order' => [
                '20146',
                [
                    [
                        'qty' => 1,
                        'description' => 'shallow ecommerce kit',
                        'width' => 480,
                        'length' => 300,
                        'depth' => 120,
                        'weight' => 900,
                    ],
                ],
            ],
            'long item order' => [
                '24108',
                [
                    [
                        'qty' => 1,
                        'description' => 'long ecommerce item',
                        'width' => 560,
                        'length' => 220,
                        'depth' => 150,
                        'weight' => 700,
                    ],
                ],
            ],
        ];
    }

    private function addPackriftSampleCartons(Packer $packer): void
    {
        foreach (self::PACKRIFT_CARTONS as $carton) {
            self::assertStringStartsWith('https://packrift.com/products/', $carton['url']);

            $packer->addBox(
                new TestBox(
                    "Packrift {$carton['sku']}",
                    $carton['width'],
                    $carton['length'],
                    $carton['depth'],
                    0,
                    $carton['width'],
                    $carton['length'],
                    $carton['depth'],
                    self::MAX_WEIGHT
                )
            );
        }
    }
}
