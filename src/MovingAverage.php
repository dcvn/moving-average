<?php

namespace Dcvn\Math\Statistics;

use DivisionByZeroError;
use Generator;
use InvalidArgumentException;
use LogicException;

class MovingAverage
{
    // Actual constant values are irrelevant and subject to change.
    const ARITHMETIC          = 1;
    const WEIGHTED_ARITHMETIC = 2;

    protected $method;

    /**
     * Number of values to take an average from.
     *
     * @var int >= 1, >= delay
     */
    protected $period;

    /**
     * Delay of output for an input key.
     *
     * @var int >= 0, <= period
     */
    protected $delay;

    /**
     * A series of weight for the set from left to right.
     *
     * @var array count() == period
     */
    protected $weights;

    /**
     * Current set (FIFO) to calculate the average for.
     * New values are appended at the end of the array,
     * Old values are removed from the beginning of the array.
     *
     * @var array count() <= period
     */
    protected $set;

    /**
     * FIFO keys for delayed output.
     *
     * @var array count() <= delay
     */
    protected $delayKeys;

    /**
     * @param string|int $method A method constant
     */
    public function __construct($method = null)
    {
        $this->setMethod($method);

        $this->reset();
    }

    /**
     * @param string|int $method A method constant
     *
     * @throws InvalidArgumentException
     */
    private function setMethod($method)
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
    public function clear()
    {
        $this->set       = [];
        $this->delayKeys = [];
    }

    /**
     * Reset to default settings (and clear data).
     */
    public function reset()
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
    public function assertValidSettings()
    {
        if ($this->validateSettings()) {
            return true;
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
    public function setDefaultWeightsForPeriod(int $defaultWeight = 1)
    {
        $this->weights = \array_fill(0, $this->period, $defaultWeight);

        return $this;
    }

    /**
     * Set the size of historic items to use for average calculation.
     *
     * @param int $period Number of items in an average set.
     */
    public function setPeriod(int $period)
    {
        $this->period = $period;

        if (empty($this->weights)) {
            $this->setDefaultWeightsForPeriod(1);
        }

        return $this;
    }

    /**
     * Set the size of historic items to use for average calculation.
     *
     * @param int $period Number of items in an average set.
     */
    public function setDelay(int $delay)
    {
        $this->delay = $delay;

        return $this;
    }

    /**
     * Set the weight for items in the set. Count should be equal to Period.
     *
     * @param array $weights The weights.
     */
    public function setWeights(array $weights)
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
     * @return array
     */
    public function getWeights(): array
    {
        return $this->weights;
    }

    /**
     * Add a value to the set, and drop the oldest value when set is full.
     *
     * @param float|int        $value The new value for the set.
     * @param string|int|float $key   The key of the new value for the set.
     */
    protected function pushValue($value)
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
     * @return string|int|float Next delayed key.
     */
    protected function pushKey($key)
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

        $denominator = \array_sum(\array_map(function ($value, $weight) {
            return $value === null ? 0 : $weight;
        }, $this->set, $weights));

        if ($denominator == 0) {
            throw new DivisionByZeroError('empty_set');
        }

        $nominator = \array_sum(\array_map(function ($value, $weight) {
            return $value === null ? 0 : $value * $weight;
        }, $this->set, $weights));

        return $nominator / $denominator;
    }

    /**
     * For delayed results, continue looping until delay is over.
     *
     * @param array $results The results array to append to (by reference).
     */
    protected function appendDelayToArray(array &$results)
    {
        for ($delay = $this->delay; $delay > 0; $delay--) {
            $delayKey = '_delay:' . $delay;

            list($avgValue, $avgKey) = $this->calculateNext(null, $delayKey);

            $results[$avgKey]= $avgValue;
        }
    }

    /**
     * For delayed results, continue looping until delay is over.
     */
    protected function appendDelayToGenerator()
    {
        for ($delay = $this->delay; $delay > 0; $delay--) {
            $delayKey = '_delay:' . $delay;

            list($avgValue, $avgKey) = $this->calculateNext(null, $delayKey);

            yield $avgKey => $avgValue;
        }
    }

    /**
     * Calculate the next average when adding a new value.
     *
     * @param float|int        $value The new value for the set.
     * @param string|int|float $key   The key of the new value for the set.
     *
     * @throws LogicException
     *
     * @return array{0:int|float,1:string|int|float} The next average, a [$value, $key] pair.
     */
    public function calculateNext($value, $key): ?array
    {
        $this->pushValue($value);

        switch ($this->method) {
            case self::ARITHMETIC:
                $average = $this->calculateSimpleAverage();
                break;
            case self::WEIGHTED_ARITHMETIC:
                $average = $this->calculateWeightedAverage();
                break;
            default:
                throw new LogicException('invalid_method');
        }

        $resultKey = $this->pushKey($key);
        if ($resultKey === null) {
            return null;
        }

        return [$average, $resultKey];
    }

    /**
     * Get calculated averages from an array.
     *
     * @param array $sources
     *
     * @return array
     */
    public function getCalculatedFromArray(array $sources): array
    {
        $this->assertValidSettings();

        $results = [];
        foreach ($sources as $key => $value) {
            list($avgValue, $avgKey) = $this->calculateNext($value, $key);
            if (! \is_null($avgKey)) {
                $results[$avgKey] = $avgValue;
            }
        }
        $this->appendDelayToArray($results);

        return $results;
    }

    /**
     * Generate averages from from an array.
     *
     * @param array $sources
     *
     * @return Generator
     */
    public function generateFromArray(array $sources): Generator
    {
        $this->assertValidSettings();

        foreach ($sources as $key => $value) {
            list($avgValue, $avgKey) = $this->calculateNext($value, $key);
            if (! \is_null($avgKey)) {
                yield $avgKey => $avgValue;
            }
        }

        yield from $this->appendDelayToGenerator();
    }

    /**
     * Get calculated averages from a generator.
     *
     * @param Generator $generator
     *
     * @return array
     */
    public function getCalculatedFromGenerator(Generator $generator): array
    {
        $this->assertValidSettings();

        $results = [];
        while ($generator->valid()) {
            list($avgValue, $avgKey) = $this->calculateNext($generator->current(), $generator->key());
            if (! \is_null($avgKey)) {
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
     * @param Generator $generator
     *
     * @return Generator
     */
    public function generateFromGenerator(Generator $generator): Generator
    {
        $this->assertValidSettings();

        while ($generator->valid()) {
            list($avgValue, $avgKey) = $this->calculateNext($generator->current(), $generator->key());
            if (! \is_null($avgKey)) {
                yield $avgKey => $avgValue;
            }

            $generator->next();
        }

        yield from $this->appendDelayToGenerator();
    }
}
