<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Execution\BenchmarkResult;
use Cline\Bench\Statistics\SummaryStatistics;

describe('BenchmarkResult', function (): void {
    it('canonicalizes parameter labels for stable identifiers', function (): void {
        $left = new BenchmarkResult(
            subject: 'transform',
            scenario: 'dto',
            competitor: 'struct',
            summary: summary(),
            samples: [1.0],
            parameters: ['size' => 'small', 'options' => ['b' => 2, 'a' => 1]],
        );
        $right = new BenchmarkResult(
            subject: 'transform',
            scenario: 'dto',
            competitor: 'struct',
            summary: summary(),
            samples: [1.0],
            parameters: ['options' => ['a' => 1, 'b' => 2], 'size' => 'small'],
        );

        expect($left->parameterLabel())->toBe('{"options":{"a":1,"b":2},"size":"small"}')
            ->and($left->parameterLabel())->toBe($right->parameterLabel())
            ->and($left->identifier())->toBe($right->identifier());
    });

    it('prefers an explicit case label over encoded parameters', function (): void {
        $result = new BenchmarkResult(
            subject: 'transform',
            scenario: 'dto',
            competitor: 'struct',
            summary: summary(),
            samples: [1.0],
            parameters: ['size' => 'small'],
            caseLabel: 'small-payload',
        );

        expect($result->parameterLabel())->toBe('small-payload')
            ->and($result->toArray()['case_label'])->toBe('small-payload');
    });
});

function summary(): SummaryStatistics
{
    return new SummaryStatistics(
        samples: 1,
        min: 1.0,
        max: 1.0,
        mean: 1.0,
        median: 1.0,
        standardDeviation: 0.0,
        relativeMarginOfError: 0.0,
        percentile75: 1.0,
        percentile95: 1.0,
        percentile99: 1.0,
        operationsPerSecond: 1.0,
    );
}
