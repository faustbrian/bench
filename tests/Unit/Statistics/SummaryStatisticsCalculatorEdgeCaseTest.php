<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Statistics\SummaryStatistics;
use Cline\Bench\Statistics\SummaryStatisticsCalculator;

describe('SummaryStatisticsCalculator edge cases', function (): void {
    it('handles a single sample deterministically', function (): void {
        $summary = new SummaryStatisticsCalculator()->summarize([42_000]);

        expect($summary->median)->toBe(42_000.0)
            ->and($summary->operationsPerSecond)->toBeGreaterThan(0.0);
    });

    it('rejects empty sample sets', function (): void {
        expect(fn (): SummaryStatistics => new SummaryStatisticsCalculator()->summarize([]))
            ->toThrow(InvalidArgumentException::class);
    });
});
