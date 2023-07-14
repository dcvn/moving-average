<?php

declare(strict_types=1);

namespace Dcvn\Tests;

use Dcvn\Math\Statistics\MovingAverage;
use Generator;
use PHPUnit\Framework\TestCase;

class MovingAverageTest extends TestCase
{
    public function testConstructing(): void
    {
        $ma1 = new MovingAverage();

        $this->assertInstanceOf(MovingAverage::class, $ma1);
    }

    /**
     * Helper: Convert an array into a Generator.
     *
     * @param array<string|int|float,int|float|null> $array
     *
     * @return Generator<string|int|float,int|float|null>
     */
    public function asGenerator(array $array): Generator
    {
        foreach ($array as $key => $value) {
            yield $key => $value;
        }
    }

    public function testSimpleCalculateNext(): void
    {
        $method = MovingAverage::ARITHMETIC;
        $mavg   = new MovingAverage($method);
        $mavg->setPeriod(1);

        foreach ([1, 2, 3, 4, 5] as $i => $value) {
            [$average, $key] = ($mavg->calculateNext($value, $i) ?? [0.0, '']);
            $this->assertEquals($value, $average);
            $this->assertEquals($i, $key);
        }
    }

    /**
     * @dataProvider dataproviderSimpleFromArray
     * @param array<string,int> $sources
     * @param array<string,float> $results
     */
    public function testSimpleCalculatedFromArray(int $period, int $delay, array $sources, array $results): void
    {
        $method = MovingAverage::ARITHMETIC;

        $mavg = new MovingAverage($method);
        $mavg->setPeriod($period)
            ->setDelay($delay);

        $averages = $mavg->getCalculatedFromArray($sources);

        $this->assertEquals($results, $averages);
    }

    /**
     * @dataProvider dataproviderSimpleFromArray
     * @param array<string,int> $sources
     * @param array<string,float> $results
     */
    public function testSimpleGenerateFromArray(int $period, int $delay, array $sources, array $results): void
    {
        $method = MovingAverage::ARITHMETIC;

        $mavg = new MovingAverage($method);
        $mavg->setPeriod($period)
            ->setDelay($delay);

        foreach ($mavg->generateFromArray($sources) as $key => $value) {
            $this->assertEquals($results[$key], $value);
        }
    }

    /**
     * @dataProvider dataproviderSimpleFromArray
     * @param array<string,int> $sources
     * @param array<string,float> $results
     */
    public function testSimpleCalculatedFromGenerator(int $period, int $delay, array $sources, array $results): void
    {
        $method = MovingAverage::ARITHMETIC;

        $mavg = new MovingAverage($method);
        $mavg->setPeriod($period)
            ->setDelay($delay);

        $generator = $this->asGenerator($sources);

        $averages = $mavg->getCalculatedFromGenerator($generator);

        $this->assertEquals($results, $averages);
    }

    /**
     * @dataProvider dataproviderSimpleFromArray
     * @param array<string,int> $sources
     * @param array<string,float> $results
     */
    public function testSimpleGenerateFromGenerator(int $period, int $delay, array $sources, array $results): void
    {
        $method = MovingAverage::ARITHMETIC;

        $mavg = new MovingAverage($method);
        $mavg->setPeriod($period)
            ->setDelay($delay);

        $generator = $this->asGenerator($sources);

        foreach ($mavg->generateFromGenerator($generator) as $key => $value) {
            $this->assertEquals($results[$key], $value);
        }
    }

    /**
     * @return Generator<string,array<string,int|array<string,int|float>>>
     */
    public function dataproviderSimpleFromArray(): Generator
    {
        yield 's1:p3-d0' => [
            'period'  => 3,
            'delay'   => 0, // default
            'sources' => [
                '13:30' => 20, //-> (20)/1       = 20.0
                '13:31' => 18, //-> (18+20)/2    = 19.0
                '13:32' => 24, //-> (24+18+20)/3 = 20.667
                '13:33' => 21, //-> (21+24+18)/3 = 21.0
                '13:34' => 15, //-> (15+21+24_/3 = 20.0
                '13:35' => 15, //-> (15+15+21)/3 = 17.0
                '13:36' => 17, //-> (17+15+15)/3 = 15.667
                '13:37' => 19, //-> (19+17+15)/3 = 17.0
                // -> (19+17)/2 = 18.0
                // -> (19)/1 = 19.0
            ],
            'results' => [
                '13:30' => (20) / 1,
                '13:31' => (18 + 20) / 2,
                '13:32' => (24 + 18 + 20) / 3,
                '13:33' => (21 + 24 + 18) / 3,
                '13:34' => (15 + 21 + 24) / 3,
                '13:35' => (15 + 15 + 21) / 3,
                '13:36' => (17 + 15 + 15) / 3,
                '13:37' => (19 + 17 + 15) / 3,
                // -> (19+17)/2
                // -> (19)/1
            ],
        ];

        yield 's2:p5-d2' => [
            'period'  => 5,
            'delay'   => 2,
            'sources' => [
                'A' => 1,
                'B' => 2,
                'C' => 3,
                'D' => 4,
                'E' => 5,
                'F' => 6,
                'G' => 7,
                // 0 => null,
                // 1 => null,
            ],
            'results' => [
                // in: A
                // in: B
                'A' => (3 + 2 + 1) / 3, // in: C
                'B' => (4 + 3 + 2 + 1) / 4, // in: D
                'C' => (5 + 4 + 3 + 2 + 1) / 5,  // in: E
                'D' => (6 + 5 + 4 + 3 + 2) / 5, // in: F
                'E' => (7 + 6 + 5 + 4 + 3) / 5, // in: G
                'F' => (7 + 6 + 5 + 4) / 4, // in: 0
                'G' => (7 + 6 + 5) / 3, // in: 1
            ],
        ];

        yield 's3:p2-d1' => [
            'period'  => 2,
            'delay'   => 1,
            'sources' => [
                'A' => 1,
                'B' => 2,
                'C' => 3,
                'D' => 4,
                // null
            ],
            'results' => [
                // null
                'A' => (2 + 1) / 2,
                'B' => (3 + 2) / 2,
                'C' => (4 + 3) / 2,
                'D' => (4) / 1,
            ],
        ];
    }

    /**
     * @dataProvider dataproviderWeightedFromArray
     * @param array<int,int> $weights
     * @param array<string,int> $sources
     * @param array<string,float> $results
     */
    public function testWeightedCalculatedFromArray(int $period, int $delay, array $weights, array $sources, array $results): void
    {
        $method = MovingAverage::WEIGHTED_ARITHMETIC;

        $mavg = new MovingAverage($method);
        $mavg->setPeriod($period)
            ->setDelay($delay)
            ->setWeights($weights);

        $averages = $mavg->getCalculatedFromArray($sources);

        $this->assertEquals($results, $averages);
    }

    /**
     * @dataProvider dataproviderWeightedFromArray
     * @param array<int,int> $weights
     * @param array<string,int> $sources
     * @param array<string,float> $results
     */
    public function testWeightedGenerateFromArray(int $period, int $delay, array $weights, array $sources, array $results): void
    {
        $method = MovingAverage::WEIGHTED_ARITHMETIC;

        $mavg = new MovingAverage($method);
        $mavg->setPeriod($period)
            ->setDelay($delay)
            ->setWeights($weights);

        foreach ($mavg->generateFromArray($sources) as $key => $value) {
            $this->assertEquals($results[$key], $value);
        }
    }

    /**
     * @dataProvider dataproviderWeightedFromArray
     * @param array<int,int> $weights
     * @param array<string,int> $sources
     * @param array<string,float> $results
     */
    public function testWeightedCalculatedFromGenerator(int $period, int $delay, array $weights, array $sources, array $results): void
    {
        $method = MovingAverage::WEIGHTED_ARITHMETIC;

        $mavg = new MovingAverage($method);
        $mavg->setPeriod($period)
            ->setDelay($delay)
            ->setWeights($weights);

        $generator = $this->asGenerator($sources);

        $averages = $mavg->getCalculatedFromGenerator($generator);

        $this->assertEquals($results, $averages);
    }

    /**
     * @dataProvider dataproviderWeightedFromArray
     * @param array<int,int> $weights
     * @param array<string,int> $sources
     * @param array<string,float> $results
     */
    public function testWeightedGenerateFromGenerator(int $period, int $delay, array $weights, array $sources, array $results): void
    {
        $method = MovingAverage::WEIGHTED_ARITHMETIC;

        $mavg = new MovingAverage($method);
        $mavg->setPeriod($period)
            ->setDelay($delay)
            ->setWeights($weights);

        $generator = $this->asGenerator($sources);

        foreach ($mavg->generateFromGenerator($generator) as $key => $value) {
            $this->assertEquals($results[$key], $value);
        }
    }

    /**
     * @return Generator<string,array<string,int|array<string|int,int|float>>>
     */
    public function dataproviderWeightedFromArray(): Generator
    {
        yield 'w1:p3-d0' => [
            'period'  => 3,
            'delay'   => 0,
            'weights' => [
                -2 => 1,
                -1 => 2,
                0  => 3,
            ],
            'sources' => [
                '13:30' => 20,
                '13:31' => 18,
                '13:32' => 24,
                '13:33' => 21,
                '13:34' => 15,
                '13:35' => 15,
                '13:36' => 17,
                '13:37' => 19,
            ],
            'results' => [
                '13:30' => (3 * 20) / (3),
                '13:31' => (3 * 18 + 2 * 20) / (3 + 2),
                '13:32' => (3 * 24 + 2 * 18 + 1 * 20) / (3 + 2 + 1),
                '13:33' => (3 * 21 + 2 * 24 + 1 * 18) / (3 + 2 + 1),
                '13:34' => (3 * 15 + 2 * 21 + 1 * 24) / (3 + 2 + 1),
                '13:35' => (3 * 15 + 2 * 15 + 1 * 21) / (3 + 2 + 1),
                '13:36' => (3 * 17 + 2 * 15 + 1 * 15) / (3 + 2 + 1),
                '13:37' => (3 * 19 + 2 * 17 + 1 * 15) / (3 + 2 + 1),
            ],
        ];

        yield 'w2:p5-d2' => [
            'period'  => 5,
            'delay'   => 2,
            'weights' => [
                -2 => 2,
                -1 => 4,
                0  => 5,
                1  => 3,
                2  => 1,
            ],
            'sources' => [
                'A' => 1,
                'B' => 2,
                'C' => 3,
                'D' => 4,
                'E' => 5,
                'F' => 6,
                'G' => 7,
                // 0 => null,
                // 1 => null,
            ],
            'results' => [
                // in: A
                // in: B
                'A' => (1 * 3 + 3 * 2 + 5 * 1) / (1 + 3 + 5), // in: C
                'B' => (1 * 4 + 3 * 3 + 5 * 2 + 4 * 1) / (1 + 3 + 5 + 4), // in: D
                'C' => (1 * 5 + 3 * 4 + 5 * 3 + 4 * 2 + 2 * 1) / (1 + 3 + 5 + 4 + 2),  // in: E
                'D' => (1 * 6 + 3 * 5 + 5 * 4 + 4 * 3 + 2 * 2) / (1 + 3 + 5 + 4 + 2), // in: F
                'E' => (1 * 7 + 3 * 6 + 5 * 5 + 4 * 4 + 2 * 3) / (1 + 3 + 5 + 4 + 2), // in: G
                'F' => (3 * 7 + 5 * 6 + 4 * 5 + 2 * 4) / (3 + 5 + 4 + 2), // in: 0
                'G' => (5 * 7 + 4 * 6 + 2 * 5) / (5 + 4 + 2), // in: 1
            ],
        ];
    }
}
