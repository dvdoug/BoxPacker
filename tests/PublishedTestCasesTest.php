<?php
/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\THPackTestItem;
use function explode;
use function fclose;
use function feof;
use function fgetcsv;
use function fgets;
use function fopen;
use function ini_set;
use function is_array;
use PHPUnit\Framework\TestCase;
use function trim;

/*
 * The testcases in this file are benchmark tests for informational purposes, not tests of correctness.
 * They exercise the library against published test data from the academic literature.
 *
 * Of note, is that academia always attempts an actual-best fit packing, which is contrary to the
 * design goal of this library which is to simulate how a human does packing arrangements.
 *
 * Also of note, is that these tests are designed to exercise an algorithm that fits the most number of
 * packages into a single box, whereas BoxPacker is designed to use multiple boxes because in real life
 * you want all of your stuff packed, not just some of it. Therefore the output of these tests are not
 * representative of real BoxPacker output, as BoxPacker is designed to distribute packages as evenly as
 * possible between boxes, instead of e.g. cramming one to the top and having a second box mostly empty.
 *
 * Test data taken from the OR Library http://people.brunel.ac.uk/~mastjjb/jeb/orlib/thpackinfo.html
 */
class PublishedTestCasesTest extends TestCase
{
    protected static $expectedResults = [];

    public static function setUpBeforeClass(): void
    {
        ini_set('memory_limit', '-1');

        $fp = fopen(__DIR__ . '/data/thpack-expected.csv', 'rb');
        while (!feof($fp)) {
            $data = fgetcsv($fp);
            if (is_array($data)) {
                self::$expectedResults[$data[0]] = $data[1];
            }
        }
        fclose($fp);
    }

    /**
     * H.T. Loh & A.Y.C. Nee, 1992, A packing algorithm for hexahedral
     * boxes, Proc. Industrial Automation 92 Conf. Singapore, 115-126.
     *
     * @dataProvider lohAndNeeData
     * @group efficiency
     */
    public function testLohAndNee($problem, $box, $items): void
    {
        $this->runPublishedTestcase($problem, $box, $items);
    }

    public function lohAndNeeData(): array
    {
        $data = [];
        $fileData = $this->thpackDecode('thpack8.txt');
        foreach ($fileData as &$problem) {
            $problem[0] = "Loh and Nee #{$problem[0]}";
            $data[$problem[0]] = $problem;
        }

        return $data;
    }

    /**
     * E.E. Bischoff and M.S.W. Ratcliff, "Issues in the development of
     *  Approaches to Container Loading", OMEGA, vol.23, no.4, (1995).
     *
     * @dataProvider bischoffData
     * @group efficiency
     */
    public function testBischoff($problem, $box, $items): void
    {
        $this->runPublishedTestcase($problem, $box, $items);
    }

    public function bischoffData(): array
    {
        $data = [];

        for ($i = 1; $i <= 7; ++$i) {
            $fileData = $this->thpackDecode("thpack{$i}.txt");
            foreach ($fileData as &$problem) {
                $problem[0] = "Bischoff #{$problem[3]}-{$problem[0]}";
                $data[$problem[0]] = $problem;
            }
        }

        return $data;
    }

    public function runPublishedTestcase($problem, Box $box, ItemList $items): void
    {
        $packer = new VolumePacker($box, $items);
        $packedBox = $packer->pack();

        $volumeUtilisation = $packedBox->getVolumeUtilisation();

        self::assertEquals(self::$expectedResults[$problem], $volumeUtilisation);
    }

    protected function thpackDecode($filename): array
    {
        $data = [];

        $handle = fopen(__DIR__ . '/data/' . $filename, 'rb');
        $problemCount = trim(fgets($handle));

        for ($p = 1; $p <= $problemCount; ++$p) {
            $problemId = explode(' ', trim(fgets($handle)))[0];
            $boxDimensions = explode(' ', trim(fgets($handle)));
            $box = new TestBox("Container {$problemId}",
                (int) $boxDimensions[0],
                (int) $boxDimensions[1],
                (int) $boxDimensions[2],
                1,
                (int) $boxDimensions[0],
                (int) $boxDimensions[1],
                (int) $boxDimensions[2],
                1);
            $itemTypeCount = trim(fgets($handle));

            $items = new ItemList();
            for ($i = 1; $i <= $itemTypeCount; ++$i) {
                $itemDimensions = explode(' ', trim(fgets($handle)));
                $item = new THPackTestItem("Item {$itemDimensions[0]}",
                    (int) $itemDimensions[1],
                    (bool) $itemDimensions[2],
                    (int) $itemDimensions[3],
                    (bool) $itemDimensions[4],
                    (int) $itemDimensions[5],
                    (bool) $itemDimensions[6]);
                for ($c = 1; $c <= $itemDimensions[7]; ++$c) {
                    $items->insert($item);
                }
            }
            $data[$problemId] = [$problemId, $box, $items, $itemTypeCount];
        }

        fclose($handle);

        return $data;
    }
}
