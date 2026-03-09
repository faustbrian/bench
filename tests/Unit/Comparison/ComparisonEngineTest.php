<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Comparison\ComparisonEngine;
use Cline\Bench\Comparison\SignificanceStatus;
use Cline\Bench\Enums\ComparisonReference;
use Cline\Bench\Execution\BenchmarkResult;
use Cline\Bench\Statistics\SummaryStatistics;

describe('ComparisonEngine', function (): void {
    it('groups results by scenario and ranks competitors by median latency', function (): void {
        $engine = new ComparisonEngine();

        $report = $engine->compare([
            new BenchmarkResult(
                subject: 'transform',
                scenario: 'dto-transform',
                competitor: 'bench',
                summary: new SummaryStatistics(
                    samples: 5,
                    min: 90_000.0,
                    max: 120_000.0,
                    mean: 100_000.0,
                    median: 100_000.0,
                    standardDeviation: 10_000.0,
                    relativeMarginOfError: 5.0,
                    percentile75: 105_000.0,
                    percentile95: 120_000.0,
                    percentile99: 120_000.0,
                    operationsPerSecond: 10_000.0,
                ),
                samples: [90_000.0, 95_000.0, 100_000.0, 105_000.0, 120_000.0],
            ),
            new BenchmarkResult(
                subject: 'transform',
                scenario: 'dto-transform',
                competitor: 'spatie-data',
                summary: new SummaryStatistics(
                    samples: 5,
                    min: 110_000.0,
                    max: 140_000.0,
                    mean: 120_000.0,
                    median: 120_000.0,
                    standardDeviation: 10_000.0,
                    relativeMarginOfError: 5.0,
                    percentile75: 125_000.0,
                    percentile95: 140_000.0,
                    percentile99: 140_000.0,
                    operationsPerSecond: 8_333.333,
                ),
                samples: [110_000.0, 115_000.0, 120_000.0, 125_000.0, 140_000.0],
            ),
        ]);

        expect($report->rows)->toHaveCount(2)
            ->and($report->rows[0]->winner)->toBe('bench')
            ->and($report->rows[0]->deltaPercentage)->toBe(0.0)
            ->and($report->rows[0]->significance?->status)->toBe(SignificanceStatus::Winner)
            ->and($report->rows[1]->winner)->toBe('bench')
            ->and(abs($report->rows[1]->deltaPercentage - 20.0))->toBeLessThan(0.001)
            ->and($report->rows[1]->significance?->pValue)->not->toBeNull()
            ->and(abs($report->geometricMeanReferenceGap - 1.2))->toBeLessThan(0.001);
    });

    it('defaults geometric mean reference gap to the closest competitor gap', function (): void {
        $engine = new ComparisonEngine();

        $report = $engine->compare([
            benchmarkResult('struct', 100_000.0, 10_000.0),
            benchmarkResult('spatie-data', 120_000.0, 8_333.333),
            benchmarkResult('bag', 300_000.0, 3_333.333),
        ]);

        expect(abs($report->geometricMeanReferenceGap - 1.2))->toBeLessThan(0.001);
    });

    it('can use the slowest competitor gap for geometric mean reference gap', function (): void {
        $engine = new ComparisonEngine();

        $report = $engine->compare([
            benchmarkResult('struct', 100_000.0, 10_000.0),
            benchmarkResult('spatie-data', 120_000.0, 8_333.333),
            benchmarkResult('bag', 300_000.0, 3_333.333),
        ], ComparisonReference::Slowest);

        expect(abs($report->geometricMeanReferenceGap - 3.0))->toBeLessThan(0.001);
    });
});

function benchmarkResult(string $competitor, float $median, float $operationsPerSecond): BenchmarkResult
{
    return new BenchmarkResult(
        subject: 'transform',
        scenario: 'dto-transform',
        competitor: $competitor,
        summary: new SummaryStatistics(
            samples: 5,
            min: $median - 10_000.0,
            max: $median + 20_000.0,
            mean: $median,
            median: $median,
            standardDeviation: 10_000.0,
            relativeMarginOfError: 5.0,
            percentile75: $median + 5_000.0,
            percentile95: $median + 20_000.0,
            percentile99: $median + 20_000.0,
            operationsPerSecond: $operationsPerSecond,
        ),
        samples: [$median - 10_000.0, $median - 5_000.0, $median, $median + 5_000.0, $median + 20_000.0],
    );
}
