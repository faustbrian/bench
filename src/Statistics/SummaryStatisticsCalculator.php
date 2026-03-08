<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Statistics;

use InvalidArgumentException;

use function array_map;
use function array_sum;
use function ceil;
use function count;
use function max;
use function min;
use function sort;
use function sqrt;
use function throw_if;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class SummaryStatisticsCalculator
{
    /**
     * @param list<float|int> $samples
     */
    public function summarize(array $samples): SummaryStatistics
    {
        throw_if($samples === [], InvalidArgumentException::class, 'At least one sample is required.');

        $values = array_map(static fn (float|int $sample): float => (float) $sample, $samples);
        sort($values);

        $sampleCount = count($values);
        $mean = array_sum($values) / $sampleCount;
        $variance = 0.0;

        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }

        $variance /= $sampleCount;
        $standardDeviation = sqrt($variance);
        $marginOfError = 1.96 * ($standardDeviation / sqrt($sampleCount));

        return new SummaryStatistics(
            samples: $sampleCount,
            min: $values[0],
            max: $values[$sampleCount - 1],
            mean: $mean,
            median: $this->percentile($values, 50),
            standardDeviation: $standardDeviation,
            relativeMarginOfError: $mean === 0.0 ? 0.0 : ($marginOfError / $mean) * 100,
            percentile75: $this->percentile($values, 75),
            percentile95: $this->percentile($values, 95),
            percentile99: $this->percentile($values, 99),
            operationsPerSecond: $this->operationsPerSecond($this->percentile($values, 50)),
        );
    }

    /**
     * @param list<float> $values
     */
    private function percentile(array $values, int $percentile): float
    {
        $count = count($values);

        if ($count === 1) {
            return $values[0];
        }

        $position = (int) ceil(($percentile / 100) * $count) - 1;
        $position = max(0, min($count - 1, $position));

        return $values[$position];
    }

    private function operationsPerSecond(float $medianNanoseconds): float
    {
        if ($medianNanoseconds <= 0.0) {
            return 0.0;
        }

        return 1_000_000_000 / $medianNanoseconds;
    }
}
