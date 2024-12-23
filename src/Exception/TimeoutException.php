<?php

/**
 * Box packing (3D bin packing, knapsack problem).
 *
 * @author Doug Wright
 */
declare(strict_types=1);

namespace DVDoug\BoxPacker\Exception;

use RuntimeException;

/**
 * Exception used when the timeout occurred
 */
class TimeoutException extends RuntimeException
{
    public function __construct(string $message, private readonly float $spentTime, private readonly float $timeout)
    {
        parent::__construct($message);
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function getSpentTime(): float
    {
        return $this->spentTime;
    }
}
