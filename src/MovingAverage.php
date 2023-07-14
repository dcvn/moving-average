<?php

declare(strict_types=1);

namespace Dcvn\Math\Statistics;

use DivisionByZeroError;
use Generator;
use InvalidArgumentException;
use LogicException;

class MovingAverage
{
    // Actual constant values are irrelevant and subject to change.
    public const ARITHMETIC          = 1;
    public const WEIGHTED_ARITHMETIC = 2;

    /**
     * Average calculation method (a class constant).
     *
     * @var int
     */
    protected int $method;

    /**
     * Number of values to take an average from.
     *
     * @var int >= 1, >= delay
     */
    protected int $period;

    /**
     * Delay of output for an input key.
     *
     * @var int >= 0, <= period
     */
    protected int $delay;

    /**
     * A series of weight for the set from left to right.
     *
     * @var array<int,int|float> count() == period
     */
    protected array $weights;

    /**
     * Current set (FIFO) to calculate the average for.
     * New values are appended at the end of the array,
     * Old values are removed from the beginning of the array.
     *
     * @var array<int|float|null> count() <= period
     */
    protected array $set;

    /**
     * FIFO keys for delayed output.
     *
     * @var array<string|int|float> count() <= delay
     */
    protected array $delayKeys;

    /**
     * @param int $method A method constant
     */
    public function __construct(int $method = null)
    {
        $this->setMethod($method);

        $this->reset();
    }

    /**
     * @param int|null $method A method constant
     *
     * @throws InvalidArgumentException
     */
    private function setMethod(?int $method): void
    {
        if (! \in_array($method, [
            null,
            self::ARITHMETIC,
            self::WEIGHTED_ARITHMETIC,
        ])) {
            throw new InvalidArgumentException('invalid_method');
        }

        $this->method = $method ?? self::ARITHMETIC;
    }

    /**
     * Clear the internal data buffers.
     */
    public function clear(): void
    {
        $this->set       = [];
        $this->delayKeys = [];
    }

    /**
     * Reset to default settings (and clear data).
     */
    public function reset(): void
    {
        $this->clear();

        $this->period  = 1;
        $this->delay   = 0;
        $this->weights = [];
    }

    /**
     * Validate combination of input settings.
     *
     * @throws InvalidArgumentException
     */
    public function assertValidSettings(): void
    {
        if ($this->validateSettings()) {
            return;
        }

        throw new InvalidArgumentException('invalid_setting_combination');
    }

    /**
     * Check if user provided settings do not clash.
     *
     * @return bool
     */
    public function validateSettings(): bool
    {
        return $this->period >= 1
            && $this->delay >= 0
            && $this->delay <= $this->period
            && \count($this->weights) == $this->period;
    }

    /**
     * Validate internal buffers.
     *
     * @return bool
     */
    public function validateBuffers(): bool
    {
        return \count($this->set) <= $this->period
            && \count($this->delayKeys) <= $this->delay;
    }

    /**
     * Set all weights to the same value..
     *
     * @param int $defaultWeight The default weight value.
     */
    public function setDefaultWeightsForPeriod(int $defaultWeight = 1): self
    {
        $this->weights = \array_fill(0, $this->period, $defaultWeight);

        return $this;
    }

    /**
     * Set the size of historic items to use for average calculation.
     *
     * @param int $period Number of items in an average set.
     */
    public function setPeriod(int $period): self
    {
        $this->period = $period;

        if (empty($this->weights)) {
            $this->setDefaultWeightsForPeriod(1);
        }

        return $this;
    }

    /**
     * Set the delay before starting to calculate average.
     *
     * @param int $delay The delay.
     */
    public function setDelay(int $delay): self
    {
        $this->delay = $delay;

        return $this;
    }

    /**
     * Set the weight for items in the set. Count should be equal to Period.
     *
     * @param array<int,int|float> $weights The weights.
     */
    public function setWeights(array $weights): self
    {
        $this->weights = $weights;

        return $this;
    }

    /**
     * Get the period.
     *
     * @return int
     */
    public function getPeriod(): int
    {
        return $this->period;
    }

    /**
     * Get the delay.
     *
     * @return int
     */
    public function getDelay(): int
    {
        return $this->delay;
    }

    /**
     * Get the weights.
     *
     * @return array<int,int|float>
     */
    public function getWeights(): array
    {
        return $this->weights;
    }

    /**
     * Add a value to the set, and drop the oldest value when set is full.
     *
     * @param float|int|null $value The new value for the set.
     */
    protected function pushValue(float|int|null $value): void
    {
        \array_push($this->set, $value); // add at end
        if (\count($this->set) > $this->period) {
            \array_shift($this->set); // remove from begin
        }
        // $this->validateBuffers();
    }

    /**
     * Push a new key to the delayed keys, and pop the oldest delayed key.
     *
     * @param string|int|float $key New key.
     *
     * @return string|int|float|null Next delayed key.
     */
    protected function pushKey(string|int|float $key): string|int|float|null
    {
        $delayedKey = null;

        \array_push($this->delayKeys, $key);
        if (\count($this->delayKeys) > $this->delay) {
            $delayedKey = \array_shift($this->delayKeys);
        }
        // $this->validateBuffers();

        return $delayedKey;
    }

    /**
     * Calculate the simple average from the current set.
     *
     * @throws DivisionByZeroError
     *
     * @return float
     */
    protected function calculateSimpleAverage(): float
    {
        $denominator =  count_values($this->set);

        if ($denominator == 0) {
            throw new DivisionByZeroError('empty_set');
        }

        $nominator = \array_sum($this->set);

        return  $nominator / $denominator;
    }

    /**
     * Calculate the weighted average from the current set.
     *
     * @throws DivisionByZeroError
     *
     * @return float
     */
    protected function calculateWeightedAverage(): float
    {
        // When the size of set < period, use right part of the weights.
        $weights = \array_slice($this->weights, $this->period - \count($this->set));

        $denominator = \array_sum(\array_map(
            fn ($value, $weight) => ($value === null ? 0 : $weight),
            $this->set,
            $weights
        ));

        if ($denominator == 0) {
            throw new DivisionByZeroError('empty_set');
        }

        $nominator = \array_sum(\array_map(
            fn ($value, $weight) => ($value === null ? 0 : $value * $weight),
            $this->set,
            $weights
        ));

        return $nominator / $denominator;
    }

    /**
     * For delayed results, continue looping until delay is over.
     *
     * @param array<string|int|float,float> $results The results array to append to (by reference).
     */
    protected function appendDelayToArray(array &$results): void
    {
        for ($delay = $this->delay; $delay > 0; $delay--) {
            $delayKey = '_delay:' . $delay;

            [$avgValue, $avgKey] = ($this->calculateNext(null, $delayKey) ?? [0.0, '']);

            $results[$avgKey]= $avgValue;
        }
    }

    /**
     * For delayed results, continue looping until delay is over.
     *
     * @return Generator<string|int|float,float>
     */
    protected function appendDelayToGenerator(): Generator
    {
        for ($delay = $this->delay; $delay > 0; $delay--) {
            $delayKey = '_delay:' . $delay;

            [$avgValue, $avgKey] = ($this->calculateNext(null, $delayKey) ?? [0.0, '']);

            yield $avgKey => $avgValue;
        }
    }

    /**
     * Calculate the next average when adding a new value.
     *
     * @param float|int|null   $value The new value for the set.
     * @param string|int|float $key   The key of the new value for the set.
     *
     * @throws LogicException
     *
     * @return array{0:float,1:string|int|float}|null The next average, a [$value, $key] pair.
     */
    public function calculateNext(float|int|null $value, string|int|float $key): ?array
    {
        $this->pushValue($value);

        $average = match ($this->method) {
            self::ARITHMETIC => $this->calculateSimpleAverage(),
            self::WEIGHTED_ARITHMETIC => $this->calculateWeightedAverage(),
            default => throw new LogicException('invalid_method')
        };

        $resultKey = $this->pushKey($key);
        if ($resultKey === null) {
            return null;
        }

        return [$average, $resultKey];
    }

    /**
     * Get calculated averages from an array.
     *
     * @param array<string|int|float,int|float|null> $sources
     *
     * @return array<string|int|float,float>
     */
    public function getCalculatedFromArray(array $sources): array
    {
        $this->assertValidSettings();

        $results = [];
        foreach ($sources as $key => $value) {
            $next = $this->calculateNext($value, $key);
            if (! \is_null($next)) {
                [$avgValue, $avgKey] = $next;
                $results[$avgKey] = $avgValue;
            }
        }
        $this->appendDelayToArray($results);

        return $results;
    }

    /**
     * Generate averages from from an array.
     *
     * @param array<string|int|float,int|float|null> $sources
     *
     * @return Generator<string|int|float,float>
     */
    public function generateFromArray(array $sources): Generator
    {
        $this->assertValidSettings();

        foreach ($sources as $key => $value) {
            $next = $this->calculateNext($value, $key);
            if (! \is_null($next)) {
                [$avgValue, $avgKey] = $next;
                yield $avgKey => $avgValue;
            }
        }

        yield from $this->appendDelayToGenerator();
    }

    /**
     * Get calculated averages from a generator.
     *
     * @param Generator<string|int|float,int|float|null> $generator
     *
     * @return array<string|int|float,float>
     */
    public function getCalculatedFromGenerator(Generator $generator): array
    {
        $this->assertValidSettings();

        $results = [];
        while ($generator->valid()) {
            $next = $this->calculateNext($generator->current(), $generator->key());
            if (! \is_null($next)) {
                [$avgValue, $avgKey] = $next;
                $results[$avgKey] = $avgValue;
            }
            $generator->next();
        }
        $this->appendDelayToArray($results);

        return $results;
    }

    /**
     * Generate averages from a generator.
     *
     * @param Generator<string|int|float,int|float|null> $generator
     *
     * @return Generator<string|int|float,float>
     */
    public function generateFromGenerator(Generator $generator): Generator
    {
        $this->assertValidSettings();

        while ($generator->valid()) {
            $next = $this->calculateNext($generator->current(), $generator->key());
            if (! \is_null($next)) {
                [$avgValue, $avgKey] = $next;
                yield $avgKey => $avgValue;
            }

            $generator->next();
        }

        yield from $this->appendDelayToGenerator();
    }
}
