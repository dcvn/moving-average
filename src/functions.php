<?php

declare(strict_types=1);

namespace Dcvn\Math\Statistics;

/**
 * Count items in an array without nulls.
 *
 * @param array<mixed> $countable
 *
 * @return int
 */
function count_values(array $countable): int
{
    return \count(\array_filter($countable, fn ($item) => $item !== null));
}
