<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Comparison;

use Cline\Bench\Enums\ComparisonReference;
use Cline\Bench\Execution\BenchmarkResult;

use function array_last;
use function count;
use function max;
use function usort;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ComparisonEngine
{
    public function __construct(
        private SignificanceCalculator $significance = new SignificanceCalculator(),
    ) {}

    /**
     * @param list<BenchmarkResult> $results
     */
    public function compare(
        array $results,
        ComparisonReference $comparisonReference = ComparisonReference::Closest,
    ): ComparisonReport {
        $grouped = [];

        foreach ($results as $result) {
            $grouped[$result->scenario][$result->subject][$result->parameterLabel()][] = $result;
        }

        $rows = [];
        $ratios = [];

        foreach ($grouped as $scenarioResults) {
            foreach ($scenarioResults as $subjectResults) {
                foreach ($subjectResults as $parameterResults) {
                    usort(
                        $parameterResults,
                        static fn (BenchmarkResult $left, BenchmarkResult $right): int => $left->summary->median <=> $right->summary->median,
                    );

                    $fastest = $parameterResults[0];
                    $reference = $this->referenceResult($parameterResults, $comparisonReference);
                    $ratios[] = $reference->summary->median / max($fastest->summary->median, 1.0);

                    foreach ($parameterResults as $result) {
                        $delta = (($result->summary->median - $fastest->summary->median) / $fastest->summary->median) * 100;
                        $referenceGap = $result->summary->median / max($fastest->summary->median, 1.0);
                        $referenceGain = $result === $fastest
                            ? 0.0
                            : (($result->summary->median - $fastest->summary->median) / $result->summary->median) * 100;

                        $rows[] = new ComparisonRow(
                            result: $result,
                            winner: $fastest->competitor,
                            deltaPercentage: max(0.0, $delta),
                            referenceGap: max(1.0, $referenceGap),
                            referenceGain: max(0.0, $referenceGain),
                            significance: $result === $fastest
                                ? 'winner'
                                : $this->significance->compare($fastest->samples, $result->samples),
                        );
                    }
                }
            }
        }

        return new ComparisonReport(
            rows: $rows,
            geometricMeanReferenceGap: $this->geometricMean($ratios),
        );
    }

    /**
     * @param list<BenchmarkResult> $parameterResults
     */
    private function referenceResult(array $parameterResults, ComparisonReference $comparisonReference): BenchmarkResult
    {
        if (count($parameterResults) < 2) {
            return $parameterResults[0];
        }

        if ($comparisonReference === ComparisonReference::Slowest || count($parameterResults) === 2) {
            return array_last($parameterResults);
        }

        return $parameterResults[1];
    }

    /**
     * @param list<float> $values
     */
    private function geometricMean(array $values): float
    {
        if ($values === []) {
            return 1.0;
        }

        $product = 1.0;

        foreach ($values as $value) {
            $product *= $value;
        }

        return $product ** (1 / count($values));
    }
}
