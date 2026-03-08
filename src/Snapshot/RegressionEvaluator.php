<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Snapshot;

use Cline\Bench\Execution\BenchmarkResult;

use function in_array;
use function max;
use function mb_rtrim;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class RegressionEvaluator
{
    public function evaluate(
        BenchmarkResult $current,
        BenchmarkResult $baseline,
        string $tolerance,
        string $metric = 'median',
    ): RegressionDecision {
        $baselineValue = $this->metricValue($baseline, $metric);
        $currentValue = $this->metricValue($current, $metric);
        $tolerancePercentage = $this->parseTolerance($tolerance);
        $deltaPercentage = $this->deltaPercentage($currentValue, $baselineValue, $metric);

        return new RegressionDecision(
            passed: $deltaPercentage <= $tolerancePercentage,
            metric: $metric,
            deltaPercentage: $deltaPercentage,
            tolerancePercentage: $tolerancePercentage,
        );
    }

    private function metricValue(BenchmarkResult $result, string $metric): float
    {
        return match ($metric) {
            'ops/s', 'operations_per_second' => $result->summary->operationsPerSecond,
            default => $result->summary->median,
        };
    }

    private function parseTolerance(string $tolerance): float
    {
        return (float) mb_rtrim($tolerance, '% ');
    }

    private function deltaPercentage(float $currentValue, float $baselineValue, string $metric): float
    {
        if (in_array($metric, ['ops/s', 'operations_per_second'], true)) {
            return (($baselineValue - $currentValue) / max($baselineValue, 1.0)) * 100;
        }

        return (($currentValue - $baselineValue) / max($baselineValue, 1.0)) * 100;
    }
}
