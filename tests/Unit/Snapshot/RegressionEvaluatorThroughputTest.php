<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Enums\Metric;
use Cline\Bench\Execution\BenchmarkResult;
use Cline\Bench\Snapshot\RegressionEvaluator;
use Cline\Bench\Statistics\SummaryStatistics;

describe('RegressionEvaluator throughput', function (): void {
    it('treats lower throughput as a regression', function (): void {
        $evaluator = new RegressionEvaluator();

        $baseline = new BenchmarkResult(
            subject: 'transform',
            scenario: 'dto-transform',
            competitor: 'bench',
            summary: new SummaryStatistics(5, 90_000.0, 110_000.0, 100_000.0, 100_000.0, 10_000.0, 5.0, 105_000.0, 110_000.0, 110_000.0, 10_000.0),
            samples: [90_000.0, 95_000.0, 100_000.0, 105_000.0, 110_000.0],
        );

        $current = new BenchmarkResult(
            subject: 'transform',
            scenario: 'dto-transform',
            competitor: 'bench',
            summary: new SummaryStatistics(5, 90_000.0, 110_000.0, 100_000.0, 100_000.0, 10_000.0, 5.0, 105_000.0, 110_000.0, 110_000.0, 9_000.0),
            samples: [90_000.0, 95_000.0, 100_000.0, 105_000.0, 110_000.0],
        );

        $decision = $evaluator->evaluate($current, $baseline, '5%', Metric::OperationsPerSecond);

        expect($decision->passed)->toBeFalse()
            ->and(abs($decision->deltaPercentage - 10.0))->toBeLessThan(0.001);
    });
});
