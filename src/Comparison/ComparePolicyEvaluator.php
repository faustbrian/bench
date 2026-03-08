<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Comparison;

use Cline\Bench\Execution\BenchmarkResult;

use function array_key_exists;
use function count;
use function max;
use function sprintf;

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
        ?float $minimumRatio,
    ): ComparePolicyDecision {
        $violations = [];

        $currentSummary = $this->suiteSummary($current);
        $baselineSummary = $this->suiteSummary($baseline);

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

        if ($minimumRatio !== null) {
            foreach ($currentSummary as $identifier => $currentRow) {
                if ($currentRow['ratio'] >= $minimumRatio) {
                    continue;
                }

                $violations[] = sprintf(
                    '%s ratio %.2fx is below required %.2fx',
                    $identifier,
                    $currentRow['ratio'],
                    $minimumRatio,
                );
            }
        }

        return new ComparePolicyDecision(
            passed: $violations === [],
            violations: $violations,
        );
    }

    /**
     * @param  list<BenchmarkResult>                              $results
     * @return array<string, array{winner: string, ratio: float}>
     */
    private function suiteSummary(array $results): array
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

                    $fastest = $parameterResults[0];
                    $slowest = $parameterResults[0];

                    foreach ($parameterResults as $result) {
                        if ($result->summary->median < $fastest->summary->median) {
                            $fastest = $result;
                        }

                        if ($result->summary->median <= $slowest->summary->median) {
                            continue;
                        }

                        $slowest = $result;
                    }

                    $summary[sprintf('%s::%s::%s', $scenario, $subject, $parameterLabel)] = [
                        'winner' => $fastest->competitor,
                        'ratio' => $slowest->summary->median / max($fastest->summary->median, 1.0),
                    ];
                }
            }
        }

        return $summary;
    }
}
