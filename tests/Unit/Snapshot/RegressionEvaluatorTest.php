<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Execution\BenchmarkResult;
use Cline\Bench\Snapshot\RegressionEvaluator;
use Cline\Bench\Statistics\SummaryStatistics;

describe('RegressionEvaluator', function (): void {
    it('fails when a median regression exceeds the configured tolerance', function (): void {
        $evaluator = new RegressionEvaluator();

        $baseline = new BenchmarkResult(
            subject: 'transform',
            scenario: 'dto-transform',
            competitor: 'bench',
            summary: new SummaryStatistics(
                samples: 5,
                min: 90_000.0,
                max: 110_000.0,
                mean: 100_000.0,
                median: 100_000.0,
                standardDeviation: 10_000.0,
                relativeMarginOfError: 5.0,
                percentile75: 105_000.0,
                percentile95: 110_000.0,
                percentile99: 110_000.0,
                operationsPerSecond: 10_000.0,
            ),
            samples: [90_000.0, 95_000.0, 100_000.0, 105_000.0, 110_000.0],
        );

        $current = new BenchmarkResult(
            subject: 'transform',
            scenario: 'dto-transform',
            competitor: 'bench',
            summary: new SummaryStatistics(
                samples: 5,
                min: 100_000.0,
                max: 125_000.0,
                mean: 111_000.0,
                median: 111_000.0,
                standardDeviation: 10_000.0,
                relativeMarginOfError: 5.0,
                percentile75: 115_000.0,
                percentile95: 125_000.0,
                percentile99: 125_000.0,
                operationsPerSecond: 9_009.009,
            ),
            samples: [100_000.0, 105_000.0, 111_000.0, 114_000.0, 125_000.0],
        );

        $decision = $evaluator->evaluate(
            current: $current,
            baseline: $baseline,
            tolerance: '5%',
        );

        expect($decision->passed)->toBeFalse()
            ->and(abs($decision->deltaPercentage - 11.0))->toBeLessThan(0.001)
            ->and($decision->metric)->toBe('median');
    });
});
