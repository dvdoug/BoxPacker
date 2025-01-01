<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker;

use DVDoug\BoxPacker\Exception\TimeoutException;

class DefaultTimeoutChecker implements TimeoutChecker
{
    private float $startTime;

    public function __construct(readonly private float $timeout)
    {
    }

    public function start(?float $startTime = null): void
    {
        $this->startTime = $startTime ?? \microtime(true);
    }

    public function throwOnTimeout(?float $currentTime = null, string $message = 'Exceeded the timeout'): void
    {
        $spentTime = ($currentTime ?? \microtime(true)) - $this->startTime;
        $isTimeout = $spentTime >= $this->timeout;
        if ($isTimeout) {
            throw new TimeoutException($message, $spentTime, $this->timeout);
        }
    }
}
