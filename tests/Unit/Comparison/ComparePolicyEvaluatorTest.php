<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Comparison\ComparePolicyEvaluator;
use Cline\Bench\Enums\ComparisonReference;
use Cline\Bench\Execution\BenchmarkResult;
use Cline\Bench\Statistics\SummaryStatistics;

describe('ComparePolicyEvaluator', function (): void {
    it('evaluates minimum reference gap against the configured comparison reference', function (): void {
        $results = [
            comparisonResult('struct', 10.0),
            comparisonResult('spatie', 20.0),
            comparisonResult('bag', 40.0),
        ];

        $closest = new ComparePolicyEvaluator()->evaluate(
            current: $results,
            baseline: $results,
            failOnWinnerChange: false,
            minimumReferenceGap: 3.0,
            comparisonReference: ComparisonReference::Closest,
        );
        $slowest = new ComparePolicyEvaluator()->evaluate(
            current: $results,
            baseline: $results,
            failOnWinnerChange: false,
            minimumReferenceGap: 3.0,
            comparisonReference: ComparisonReference::Slowest,
        );

        expect($closest->passed)->toBeFalse()
            ->and($closest->violations)->toContain('dto::transform::default reference gap 2.00x is below required 3.00x')
            ->and($slowest->passed)->toBeTrue()
            ->and($slowest->violations)->toBe([]);
    });
});

function comparisonResult(string $competitor, float $median): BenchmarkResult
{
    return new BenchmarkResult(
        subject: 'transform',
        scenario: 'dto',
        competitor: $competitor,
        summary: new SummaryStatistics(
            samples: 3,
            min: $median,
            max: $median,
            mean: $median,
            median: $median,
            standardDeviation: 0.0,
            relativeMarginOfError: 0.0,
            percentile75: $median,
            percentile95: $median,
            percentile99: $median,
            operationsPerSecond: 1_000_000_000.0 / $median,
        ),
        samples: [$median, $median, $median],
    );
}
