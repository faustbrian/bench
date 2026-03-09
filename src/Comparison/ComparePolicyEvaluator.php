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

use function array_key_exists;
use function array_last;
use function count;
use function max;
use function sprintf;
use function usort;

/**
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class ComparePolicyEvaluator
{
    /**
     * @param list<BenchmarkResult> $current
     * @param list<BenchmarkResult> $baseline
     */
    public function evaluate(
        array $current,
        array $baseline,
        bool $failOnWinnerChange,
        ?float $minimumReferenceGap,
        ComparisonReference $comparisonReference,
    ): ComparePolicyDecision {
        $violations = [];

        $currentSummary = $this->suiteSummary($current, $comparisonReference);
        $baselineSummary = $this->suiteSummary($baseline, $comparisonReference);

        if ($failOnWinnerChange) {
            foreach ($currentSummary as $identifier => $currentRow) {
                if (!array_key_exists($identifier, $baselineSummary)) {
                    continue;
                }

                $baselineRow = $baselineSummary[$identifier];

                if ($currentRow['winner'] === $baselineRow['winner']) {
                    continue;
                }

                $violations[] = sprintf(
                    '%s winner changed from %s to %s',
                    $identifier,
                    $baselineRow['winner'],
                    $currentRow['winner'],
                );
            }
        }

        if ($minimumReferenceGap !== null) {
            foreach ($currentSummary as $identifier => $currentRow) {
                if ($currentRow['reference_gap'] >= $minimumReferenceGap) {
                    continue;
                }

                $violations[] = sprintf(
                    '%s reference gap %.2fx is below required %.2fx',
                    $identifier,
                    $currentRow['reference_gap'],
                    $minimumReferenceGap,
                );
            }
        }

        return new ComparePolicyDecision(
            passed: $violations === [],
            violations: $violations,
        );
    }

    /**
     * @param  list<BenchmarkResult>                                      $results
     * @return array<string, array{winner: string, reference_gap: float}>
     */
    private function suiteSummary(array $results, ComparisonReference $comparisonReference): array
    {
        $grouped = [];

        foreach ($results as $result) {
            $grouped[$result->scenario][$result->subject][$result->parameterLabel()][] = $result;
        }

        $summary = [];

        foreach ($grouped as $scenario => $scenarioResults) {
            foreach ($scenarioResults as $subject => $subjectResults) {
                foreach ($subjectResults as $parameterLabel => $parameterResults) {
                    if (count($parameterResults) < 2) {
                        continue;
                    }

                    usort(
                        $parameterResults,
                        static fn (BenchmarkResult $left, BenchmarkResult $right): int => $left->summary->median <=> $right->summary->median,
                    );

                    $fastest = $parameterResults[0];
                    $reference = $this->referenceResult($parameterResults, $comparisonReference);

                    $summary[sprintf('%s::%s::%s', $scenario, $subject, $parameterLabel)] = [
                        'winner' => $fastest->competitor,
                        'reference_gap' => $reference->summary->median / max($fastest->summary->median, 1.0),
                    ];
                }
            }
        }

        return $summary;
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
}
