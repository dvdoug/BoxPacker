<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Exception\TimeoutException;

interface TimeoutChecker
{
    public function __construct(float $timeout);

    public function start(?float $startTime = null): void;

    /**
     * @throws TimeoutException
     */
    public function throwOnTimeout(?float $currentTime = null, string $message = 'Exceeded the timeout'): void;
}
