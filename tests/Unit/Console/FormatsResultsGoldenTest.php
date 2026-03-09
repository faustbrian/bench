<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Console\Concerns\FormatsResults;
use Cline\Bench\Enums\AssertionOperator;
use Cline\Bench\Enums\Metric;
use Cline\Bench\Execution\AssertionResult;
use Cline\Bench\Execution\BenchmarkResult;
use Cline\Bench\Statistics\SummaryStatistics;

describe('FormatsResults golden outputs', function (): void {
    it('keeps table and markdown rendering stable for rich run outputs', function (): void {
        $renderer = new class()
        {
            use FormatsResults;

            public function table(array $results): string
            {
                return $this->asTable($results);
            }

            public function markdown(array $results): string
            {
                return $this->asMarkdown($results);
            }
        };

        $results = goldenResults();

        expect(mb_rtrim($renderer->table($results)))->toBe(mb_rtrim((string) file_get_contents(__DIR__.'/../../Fixtures/Golden/run-table.txt')))
            ->and(mb_rtrim($renderer->markdown($results)))->toBe(mb_rtrim((string) file_get_contents(__DIR__.'/../../Fixtures/Golden/run-markdown.md')));
    });

    it('keeps comparison table and markdown rendering stable', function (): void {
        $renderer = new class()
        {
            use FormatsResults;

            public function table(array $results, array $baseline): string
            {
                return $this->asComparisonTable($results, $baseline);
            }

            public function markdown(array $results, array $baseline): string
            {
                return $this->asComparisonMarkdown($results, $baseline);
            }
        };

        $current = goldenResults();
        $baseline = goldenBaselineResults();

        expect(mb_rtrim($renderer->table($current, $baseline)))->toBe(mb_rtrim((string) file_get_contents(__DIR__.'/../../Fixtures/Golden/comparison-table.txt')))
            ->and(mb_rtrim($renderer->markdown($current, $baseline)))->toBe(mb_rtrim((string) file_get_contents(__DIR__.'/../../Fixtures/Golden/comparison-markdown.md')));
    });

    it('renders struct first in multi-competitor comparison summaries', function (): void {
        $renderer = new class()
        {
            use FormatsResults;

            public function table(array $results): string
            {
                return $this->asTable($results);
            }
        };

        $table = $renderer->table(multiCompetitorGoldenResults());

        expect($table)->toContain('│ Benchmark')
            ->and($table)->toContain('│   Struct │       Bag │    Spatie │  Valinor │ Winner  │ Closest Gap │ Closest Gain │')
            ->and($table)->toContain('│ Profile collection transformation │ 42.000μs │ 275.000μs │ 190.000μs │ 30.000μs │ Valinor │       1.40x │        28.6% │')
            ->and($table)->toContain('│ Struct Ops/s │ Bag Ops/s │ Spatie Ops/s │ Valinor Ops/s │');
    });

    it('uses configured preferred competitor ordering before alphabetical fallback', function (): void {
        $renderer = new class()
        {
            use FormatsResults;

            public function table(array $results): string
            {
                return $this->asTable($results);
            }

            protected function preferredCompetitors(): array
            {
                return ['valinor', 'struct'];
            }
        };

        $table = $renderer->table(multiCompetitorGoldenResults());

        expect($table)->toContain('│  Valinor │   Struct │       Bag │    Spatie │ Winner  │ Closest Gap │ Closest Gain │')
            ->and($table)->toContain('│ Profile collection transformation │ 30.000μs │ 42.000μs │ 275.000μs │ 190.000μs │ Valinor │       1.40x │        28.6% │')
            ->and($table)->toContain('│ Valinor Ops/s │ Struct Ops/s │ Bag Ops/s │ Spatie Ops/s │');
    });

    it('can render summary spread against the slowest competitor', function (): void {
        $renderer = new class()
        {
            use FormatsResults;

            public function table(array $results): string
            {
                return $this->asTable($results);
            }

            protected function comparisonReference(): string
            {
                return 'slowest';
            }
        };

        $table = $renderer->table(multiCompetitorGoldenResults());

        expect($table)->toContain('│ Profile collection transformation │ 42.000μs │ 275.000μs │ 190.000μs │ 30.000μs │ Valinor │        9.17x │        89.1% │');
    });

    it('uses configurable european-style number formatting', function (): void {
        $renderer = new class()
        {
            use FormatsResults;

            public function table(array $results): string
            {
                return $this->asTable($results);
            }

            protected function decimalSeparator(): string
            {
                return ',';
            }

            protected function thousandsSeparator(): string
            {
                return '.';
            }

            protected function durationDecimals(): int
            {
                return 0;
            }

            protected function ratioDecimals(): int
            {
                return 3;
            }

            protected function percentageDecimals(): int
            {
                return 2;
            }

            protected function operationsDecimals(): int
            {
                return 0;
            }
        };

        $table = $renderer->table(multiCompetitorGoldenResults());

        expect($table)
            ->toContain('42μs')
            ->and($table)->toContain('1,400x')
            ->and($table)->toContain('28,57%')
            ->and($table)->toContain('23.810/s');
    });
});

/**
 * @return list<BenchmarkResult>
 */
function goldenResults(): array
{
    return [
        new BenchmarkResult(
            subject: 'collection-transformation',
            scenario: 'baloo-data',
            competitor: 'struct',
            summary: goldenSummary(
                median: 100.0,
                p95: 120.0,
                p99: 125.0,
                operationsPerSecond: 10_000_000.0,
            ),
            samples: [95.0, 100.0, 105.0, 120.0, 125.0],
            groups: ['baloo', 'dto', 'comparison'],
            assertions: [
                new AssertionResult(
                    metric: Metric::Median,
                    operator: AssertionOperator::LessThan,
                    expected: 150.0,
                    actual: 100.0,
                    passed: true,
                ),
            ],
            regressionMetric: Metric::Median,
            regressionTolerance: '5%',
        ),
        new BenchmarkResult(
            subject: 'collection-transformation',
            scenario: 'baloo-data',
            competitor: 'spatie',
            summary: goldenSummary(
                median: 120.0,
                p95: 145.0,
                p99: 150.0,
                operationsPerSecond: 8_333_333.333,
            ),
            samples: [110.0, 120.0, 125.0, 145.0, 150.0],
            groups: ['baloo', 'dto', 'comparison'],
            assertions: [
                new AssertionResult(
                    metric: Metric::Median,
                    operator: AssertionOperator::LessThan,
                    expected: 170.0,
                    actual: 120.0,
                    passed: true,
                ),
            ],
            regressionMetric: Metric::Median,
            regressionTolerance: '5%',
        ),
    ];
}

/**
 * @return list<BenchmarkResult>
 */
function goldenBaselineResults(): array
{
    return [
        new BenchmarkResult(
            subject: 'collection-transformation',
            scenario: 'baloo-data',
            competitor: 'struct',
            summary: goldenSummary(
                median: 110.0,
                p95: 130.0,
                p99: 140.0,
                operationsPerSecond: 9_090_909.091,
            ),
            samples: [100.0, 110.0, 115.0, 130.0, 140.0],
            groups: ['baloo', 'dto', 'comparison'],
            assertions: [
                new AssertionResult(
                    metric: Metric::Median,
                    operator: AssertionOperator::LessThan,
                    expected: 150.0,
                    actual: 110.0,
                    passed: true,
                ),
            ],
            regressionMetric: Metric::Median,
            regressionTolerance: '5%',
        ),
        new BenchmarkResult(
            subject: 'collection-transformation',
            scenario: 'baloo-data',
            competitor: 'spatie',
            summary: goldenSummary(
                median: 130.0,
                p95: 150.0,
                p99: 160.0,
                operationsPerSecond: 7_692_307.692,
            ),
            samples: [120.0, 130.0, 135.0, 150.0, 160.0],
            groups: ['baloo', 'dto', 'comparison'],
            assertions: [
                new AssertionResult(
                    metric: Metric::Median,
                    operator: AssertionOperator::LessThan,
                    expected: 170.0,
                    actual: 130.0,
                    passed: true,
                ),
            ],
            regressionMetric: Metric::Median,
            regressionTolerance: '5%',
        ),
    ];
}

/**
 * @return list<BenchmarkResult>
 */
function multiCompetitorGoldenResults(): array
{
    return [
        new BenchmarkResult(
            subject: 'profile-collection-transformation',
            scenario: 'baloo-profile',
            competitor: 'spatie',
            summary: goldenSummary(
                median: 190_000.0,
                p95: 195_000.0,
                p99: 198_000.0,
                operationsPerSecond: 5_263.158,
            ),
            samples: [180_000.0, 185_000.0, 190_000.0, 195_000.0, 198_000.0],
        ),
        new BenchmarkResult(
            subject: 'profile-collection-transformation',
            scenario: 'baloo-profile',
            competitor: 'bag',
            summary: goldenSummary(
                median: 275_000.0,
                p95: 280_000.0,
                p99: 282_000.0,
                operationsPerSecond: 3_636.364,
            ),
            samples: [260_000.0, 270_000.0, 275_000.0, 280_000.0, 282_000.0],
        ),
        new BenchmarkResult(
            subject: 'profile-collection-transformation',
            scenario: 'baloo-profile',
            competitor: 'struct',
            summary: goldenSummary(
                median: 42_000.0,
                p95: 43_000.0,
                p99: 43_500.0,
                operationsPerSecond: 23_809.524,
            ),
            samples: [40_500.0, 41_000.0, 42_000.0, 43_000.0, 43_500.0],
        ),
        new BenchmarkResult(
            subject: 'profile-collection-transformation',
            scenario: 'baloo-profile',
            competitor: 'valinor',
            summary: goldenSummary(
                median: 30_000.0,
                p95: 31_000.0,
                p99: 31_500.0,
                operationsPerSecond: 33_333.333,
            ),
            samples: [28_000.0, 29_000.0, 30_000.0, 31_000.0, 31_500.0],
        ),
    ];
}

function goldenSummary(float $median, float $p95, float $p99, float $operationsPerSecond): SummaryStatistics
{
    return new SummaryStatistics(
        samples: 5,
        min: $median - 5.0,
        max: $p99,
        mean: $median + 1.0,
        median: $median,
        standardDeviation: 10.0,
        relativeMarginOfError: 2.0,
        percentile75: $median + 10.0,
        percentile95: $p95,
        percentile99: $p99,
        operationsPerSecond: $operationsPerSecond,
    );
}
