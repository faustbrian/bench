<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Statistics\SummaryStatisticsCalculator;

describe('SummaryStatisticsCalculator', function (): void {
    it('calculates rich latency and throughput metrics from raw samples', function (): void {
        $summary = new SummaryStatisticsCalculator()->summarize([
            100_000,
            105_000,
            110_000,
            120_000,
            150_000,
        ]);

        expect($summary->samples)->toBe(5)
            ->and($summary->min)->toBe(100_000.0)
            ->and($summary->max)->toBe(150_000.0)
            ->and($summary->mean)->toBe(117_000.0)
            ->and($summary->median)->toBe(110_000.0)
            ->and($summary->percentile75)->toBe(120_000.0)
            ->and($summary->percentile95)->toBe(150_000.0)
            ->and($summary->percentile99)->toBe(150_000.0)
            ->and(abs($summary->operationsPerSecond - 9_090.909))->toBeLessThan(0.001)
            ->and($summary->relativeMarginOfError)->toBeGreaterThan(0.0);
    });
});
