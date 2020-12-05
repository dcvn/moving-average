<?php

namespace Dcvn\Math\Statistics;

/**
 * Count items in an array without nulls.
 *
 * @param  array
 *
 * @return int
 */
function count_values(array $countable): int
{
    return \count(\array_filter($countable, function ($item) {
        return $item !== null;
    }));
}
