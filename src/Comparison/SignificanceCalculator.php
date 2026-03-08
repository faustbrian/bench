<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Comparison;

use function abs;
use function count;
use function exp;
use function max;
use function min;
use function sprintf;
use function sqrt;
use function usort;

/**
 * Mann-Whitney U test with a normal approximation.
 * @author Brian Faust <brian@cline.sh>
 */
final class SignificanceCalculator
{
    /**
     * @param list<float> $baseline
     * @param list<float> $candidate
     */
    public function compare(array $baseline, array $candidate): string
    {
        if (count($baseline) < 2 || count($candidate) < 2) {
            return 'n/a';
        }

        $pValue = $this->pValue($baseline, $candidate);

        if ($pValue < 0.05) {
            return sprintf('significant (p=%.3f)', $pValue);
        }

        return sprintf('ns (p=%.3f)', $pValue);
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    private function pValue(array $left, array $right): float
    {
        $leftCount = count($left);
        $rightCount = count($right);
        $values = [];

        foreach ($left as $value) {
            $values[] = ['sample' => $value, 'group' => 'left'];
        }

        foreach ($right as $value) {
            $values[] = ['sample' => $value, 'group' => 'right'];
        }

        usort(
            $values,
            static fn (array $first, array $second): int => $first['sample'] <=> $second['sample'],
        );

        $rankSum = 0.0;
        $index = 0;
        $count = count($values);

        while ($index < $count) {
            $end = $index;

            while (
                $end + 1 < $count
                && $values[$end + 1]['sample'] === $values[$index]['sample']
            ) {
                ++$end;
            }

            $averageRank = (($index + 1) + ($end + 1)) / 2;

            for ($position = $index; $position <= $end; ++$position) {
                if ($values[$position]['group'] !== 'left') {
                    continue;
                }

                $rankSum += $averageRank;
            }

            $index = $end + 1;
        }

        $uStatistic = $rankSum - (($leftCount * ($leftCount + 1)) / 2);
        $mean = ($leftCount * $rightCount) / 2;
        $standardDeviation = sqrt(($leftCount * $rightCount * ($leftCount + $rightCount + 1)) / 12);

        if ($standardDeviation === 0.0) {
            return 1.0;
        }

        $zScore = ($uStatistic - $mean) / $standardDeviation;

        return max(0.0, min(1.0, 2 * (1 - $this->normalCdf(abs($zScore)))));
    }

    private function normalCdf(float $value): float
    {
        $coefficient = 1 / (1 + 0.231_641_9 * $value);
        $density = (1 / sqrt(2 * 3.141_592_653_589_793)) * exp(-0.5 * $value * $value);
        $series = 0.319_381_530 * $coefficient
            - 0.356_563_782 * ($coefficient ** 2)
            + 1.781_477_937 * ($coefficient ** 3)
            - 1.821_255_978 * ($coefficient ** 4)
            + 1.330_274_429 * ($coefficient ** 5);

        return 1 - ($density * $series);
    }
}
