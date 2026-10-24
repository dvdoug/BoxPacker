<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker\Playground;

use DVDoug\BoxPacker\DefaultTimeoutChecker;
use DVDoug\BoxPacker\Exception\NoBoxesAvailableException;
use DVDoug\BoxPacker\ItemList;
use DVDoug\BoxPacker\PackedBoxList;
use DVDoug\BoxPacker\PackedItem;
use DVDoug\BoxPacker\Packer;
use DVDoug\BoxPacker\Rotation;
use InvalidArgumentException;
use JsonException;
use Throwable;

use function array_is_list;
use function array_key_exists;
use function array_map;
use function array_slice;
use function array_values;
use function file_get_contents;
use function is_array;
use function is_file;
use function is_numeric;
use function ini_set;
use function is_string;
use function iterator_to_array;
use function json_decode;
use function json_encode;
use function spl_object_id;
use function str_ends_with;
use function dirname;
use function is_bool;
use function is_int;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PHP_INT_MAX;

/**
 * Pack one playground problem and print the visualiser payload as JSON.
 *
 * CLI: problem JSON on stdin, or a file path in argv.
 * The docs page writes /boxpacker/problem.json before each run. That runtime
 * does not set $argv, and HTML error pages would make the JSON unreadable.
 * Same item and box arrays as PackedBoxList::generateVisualisationURL().
 */
ini_set('display_errors', '0');
ini_set('html_errors', '0');

$repoAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (is_file($repoAutoload)) {
    require $repoAutoload;
} else {
    require dirname(__DIR__) . '/autoload.php';
}

require_once __DIR__ . '/PlaygroundBox.php';
require_once __DIR__ . '/PlaygroundItem.php';

/**
 * @param array<int, mixed> $argv
 */
function readProblem(array $argv): string
{
    // The browser runtime writes the problem here before each run.
    if (is_file('/boxpacker/problem.json')) {
        $contents = file_get_contents('/boxpacker/problem.json');
        if ($contents === false || $contents === '') {
            throw new InvalidArgumentException('Could not read the problem file');
        }

        return $contents;
    }

    foreach (array_slice($argv, 1) as $arg) {
        if (!is_string($arg) || !is_file($arg)) {
            continue;
        }
        if (str_ends_with($arg, '.php')) {
            continue;
        }
        $contents = file_get_contents($arg);
        if ($contents === false) {
            throw new InvalidArgumentException('Could not read the problem file');
        }

        return $contents;
    }

    $stdin = file_get_contents('php://stdin');
    if ($stdin === false || $stdin === '') {
        throw new InvalidArgumentException('No packing problem was provided');
    }

    return $stdin;
}

/**
 * @param array<string, mixed> $problem
 *
 * @return array{items: list<array{0: string, 1: int, 2: int, 3: int, 4: int, 5: int}>, boxes: list<array{0: string, 1: int, 2: int, 3: int, 4: list<array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int}>}>, unpacked: list<array{description: string, quantity: int}>, utilisation: float|null, error: null}
 */
function packProblem(array $problem): array
{
    if (!isset($problem['boxes']) || !is_array($problem['boxes']) || !array_is_list($problem['boxes'])) {
        throw new InvalidArgumentException('boxes must be a list');
    }
    if (!isset($problem['items']) || !is_array($problem['items']) || !array_is_list($problem['items'])) {
        throw new InvalidArgumentException('items must be a list');
    }
    if ($problem['boxes'] === []) {
        throw new InvalidArgumentException('Add at least one box');
    }
    if ($problem['items'] === []) {
        throw new InvalidArgumentException('Add at least one item');
    }

    $options = $problem['options'] ?? [];
    if (!is_array($options)) {
        throw new InvalidArgumentException('options must be an object');
    }

    $packer = new Packer();
    $balanceWeight = $options['balanceWeight'] ?? true;
    if (!is_bool($balanceWeight)) {
        throw new InvalidArgumentException('balanceWeight must be true or false');
    }
    $packer->setMaxBoxesToBalanceWeight($balanceWeight ? 12 : 0);

    $strictItemOrdering = $options['strictItemOrdering'] ?? false;
    if (!is_bool($strictItemOrdering)) {
        throw new InvalidArgumentException('strictItemOrdering must be true or false');
    }
    $packer->beStrictAboutItemOrdering($strictItemOrdering);

    $throwOnUnpackable = $options['throwOnUnpackable'] ?? false;
    if (!is_bool($throwOnUnpackable)) {
        throw new InvalidArgumentException('throwOnUnpackable must be true or false');
    }
    $packer->throwOnUnpackableItem($throwOnUnpackable);

    if (array_key_exists('timeoutSeconds', $options) && $options['timeoutSeconds'] !== null) {
        if (!is_numeric($options['timeoutSeconds']) || (float) $options['timeoutSeconds'] < 0) {
            throw new InvalidArgumentException('timeoutSeconds must be a non-negative number or null');
        }
        $packer->setTimeoutChecker(new DefaultTimeoutChecker((float) $options['timeoutSeconds']));
    }

    foreach ($problem['boxes'] as $index => $box) {
        if (!is_array($box)) {
            throw new InvalidArgumentException('Box ' . ($index + 1) . ' must be an object');
        }
        $quantity = unlimitedQuantity($box);
        $packer->addBox(new PlaygroundBox(
            reference: requireText($box, 'reference', 'Box ' . ($index + 1)),
            outerWidth: positiveInt($box, 'outerWidth', 'Box ' . ($index + 1)),
            outerLength: positiveInt($box, 'outerLength', 'Box ' . ($index + 1)),
            outerDepth: positiveInt($box, 'outerDepth', 'Box ' . ($index + 1)),
            emptyWeight: nonNegativeInt($box, 'emptyWeight', 'Box ' . ($index + 1)),
            innerWidth: positiveInt($box, 'innerWidth', 'Box ' . ($index + 1)),
            innerLength: positiveInt($box, 'innerLength', 'Box ' . ($index + 1)),
            innerDepth: positiveInt($box, 'innerDepth', 'Box ' . ($index + 1)),
            maxWeight: nonNegativeInt($box, 'maxWeight', 'Box ' . ($index + 1)),
            quantityAvailable: $quantity,
        ));
    }

    foreach ($problem['items'] as $index => $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Item ' . ($index + 1) . ' must be an object');
        }
        $label = 'Item ' . ($index + 1);
        $packer->addItem(new PlaygroundItem(
            description: requireText($item, 'description', $label),
            width: positiveInt($item, 'width', $label),
            length: positiveInt($item, 'length', $label),
            depth: positiveInt($item, 'depth', $label),
            weight: nonNegativeInt($item, 'weight', $label),
            allowedRotation: rotation($item, $label),
        ), positiveInt($item, 'quantity', $label));
    }

    $packedBoxes = $packer->pack();
    $payload = visualiserPayload($packedBoxes);
    $payload['unpacked'] = summariseItems($packer->getUnpackedItems());
    $payload['utilisation'] = $packedBoxes->count() > 0 ? $packedBoxes->getVolumeUtilisation() : null;
    $payload['error'] = null;

    return $payload;
}

/**
 * @param array<string, mixed> $box
 */
function unlimitedQuantity(array $box): int
{
    if (!array_key_exists('quantity', $box) || $box['quantity'] === null) {
        return PHP_INT_MAX;
    }

    return positiveInt($box, 'quantity', 'Box');
}

/**
 * @param array<string, mixed> $row
 */
function requireText(array $row, string $key, string $label): string
{
    if (!isset($row[$key]) || !is_string($row[$key]) || $row[$key] === '') {
        throw new InvalidArgumentException($label . ' needs a ' . $key);
    }

    return $row[$key];
}

/**
 * @param array<string, mixed> $row
 */
function positiveInt(array $row, string $key, string $label): int
{
    $value = integerValue($row, $key, $label);
    if ($value < 1) {
        throw new InvalidArgumentException($label . ' ' . $key . ' must be at least 1');
    }

    return $value;
}

/**
 * @param array<string, mixed> $row
 */
function nonNegativeInt(array $row, string $key, string $label): int
{
    $value = integerValue($row, $key, $label);
    if ($value < 0) {
        throw new InvalidArgumentException($label . ' ' . $key . ' must be at least 0');
    }

    return $value;
}

/**
 * @param array<string, mixed> $row
 */
function integerValue(array $row, string $key, string $label): int
{
    if (!array_key_exists($key, $row) || !is_int($row[$key])) {
        throw new InvalidArgumentException($label . ' ' . $key . ' must be an integer');
    }

    return (int) $row[$key];
}

/**
 * @param array<string, mixed> $row
 */
function rotation(array $row, string $label): Rotation
{
    if (!isset($row['rotation']) || !is_string($row['rotation'])) {
        throw new InvalidArgumentException($label . ' rotation must be BestFit, KeepFlat, or Never');
    }

    return match ($row['rotation']) {
        'BestFit' => Rotation::BestFit,
        'KeepFlat' => Rotation::KeepFlat,
        'Never' => Rotation::Never,
        default => throw new InvalidArgumentException($label . ' rotation must be BestFit, KeepFlat, or Never'),
    };
}

/**
 * @return list<array{description: string, quantity: int}>
 */
function summariseItems(ItemList $items): array
{
    $counts = [];
    foreach ($items as $item) {
        $id = spl_object_id($item);
        if (!isset($counts[$id])) {
            $counts[$id] = ['description' => $item->getDescription(), 'quantity' => 0];
        }
        ++$counts[$id]['quantity'];
    }

    return array_values($counts);
}

/**
 * @return array{items: list<array{0: string, 1: int, 2: int, 3: int, 4: int, 5: int}>, boxes: list<array{0: string, 1: int, 2: int, 3: int, 4: list<array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int}>}>}
 */
function visualiserPayload(PackedBoxList $packedBoxes): array
{
    $items = [];
    foreach ($packedBoxes as $packedBox) {
        $items = [...$items, ...$packedBox->items->asItemArray()];
    }

    $dedupedItems = $splIdToIntMap = [];
    $splIdIndex = 0;
    foreach ($items as $item) {
        $id = spl_object_id($item);
        if (!isset($splIdToIntMap[$id])) {
            $splIdToIntMap[$id] = $splIdIndex++;
        }
        $dedupedItems[$splIdToIntMap[$id]] = $item;
    }

    $data = ['items' => [], 'boxes' => []];
    foreach ($dedupedItems as $index => $item) {
        $data['items'][$index] = [
            $item->getDescription(),
            $item->getWidth(),
            $item->getLength(),
            $item->getDepth(),
            $item->getWeight(),
            $item->getAllowedRotation()->value,
        ];
    }

    foreach ($packedBoxes as $packedBox) {
        $data['boxes'][] = [
            $packedBox->box->getReference(),
            $packedBox->box->getInnerWidth(),
            $packedBox->box->getInnerLength(),
            $packedBox->box->getInnerDepth(),
            array_map(
                static fn (PackedItem $packedItem) => [
                    $splIdToIntMap[spl_object_id($packedItem->item)],
                    $packedItem->x,
                    $packedItem->y,
                    $packedItem->z,
                    $packedItem->width,
                    $packedItem->length,
                    $packedItem->depth,
                ],
                iterator_to_array($packedBox->items),
            ),
        ];
    }

    return $data;
}

/**
 * @param array{items: list<mixed>, boxes: list<mixed>, unpacked: list<array{description: string, quantity: int}>, utilisation: float|null, error: string|null} $payload
 */
function emit(array $payload): void
{
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

try {
    // Wasm php.run() does not define $argv. Reading it warns, then the type
    // check throws, and the warning is printed ahead of the JSON result.
    $arguments = isset($argv) && is_array($argv) ? $argv : [];
    $decoded = json_decode(readProblem($arguments), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Problem must be a JSON object');
    }
    emit(packProblem($decoded));
} catch (NoBoxesAvailableException $exception) {
    emit([
        'items' => [],
        'boxes' => [],
        'unpacked' => summariseItems($exception->getAffectedItems()),
        'utilisation' => null,
        'error' => $exception->getMessage(),
    ]);
} catch (JsonException|InvalidArgumentException|Throwable $exception) {
    emit([
        'items' => [],
        'boxes' => [],
        'unpacked' => [],
        'utilisation' => null,
        'error' => $exception->getMessage(),
    ]);
}
