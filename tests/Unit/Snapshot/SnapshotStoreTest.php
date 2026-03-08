<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Execution\BenchmarkResult;
use Cline\Bench\Snapshot\SnapshotStore;
use Cline\Bench\Statistics\SummaryStatistics;

describe('SnapshotStore', function (): void {
    it('persists snapshots with results and environment metadata', function (): void {
        $directory = sys_get_temp_dir().'/bench-tests-'.bin2hex(random_bytes(8));
        $store = new SnapshotStore($directory);

        $store->save(
            name: 'baseline',
            results: [
                new BenchmarkResult(
                    subject: 'transform',
                    scenario: 'dto-transform',
                    competitor: 'bench',
                    summary: new SummaryStatistics(
                        samples: 3,
                        min: 100_000.0,
                        max: 120_000.0,
                        mean: 110_000.0,
                        median: 110_000.0,
                        standardDeviation: 10_000.0,
                        relativeMarginOfError: 5.0,
                        percentile75: 120_000.0,
                        percentile95: 120_000.0,
                        percentile99: 120_000.0,
                        operationsPerSecond: 9_090.909,
                    ),
                    samples: [100_000.0, 110_000.0, 120_000.0],
                ),
            ],
            metadata: ['php_version' => \PHP_VERSION],
        );

        $snapshot = $store->load('baseline');

        expect($snapshot->name)->toBe('baseline')
            ->and($snapshot->metadata['php_version'])->toBe(\PHP_VERSION)
            ->and($snapshot->results)->toHaveCount(1)
            ->and($snapshot->results[0]->summary->median)->toBe(110_000.0);
    });
});
