<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Console\Concerns;

use Cline\Bench\Comparison\ComparisonEngine;
use Cline\Bench\Comparison\ComparisonRow;
use Cline\Bench\Comparison\SignificanceCalculator;
use Cline\Bench\Execution\BenchmarkResult;
use Cline\Bench\Snapshot\RegressionEvaluator;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const PHP_EOL;
use const STR_PAD_LEFT;

use function array_fill_keys;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_search;
use function array_values;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function json_encode;
use function max;
use function mb_rtrim;
use function mb_strlen;
use function mb_strtoupper;
use function mb_substr;
use function min;
use function number_format;
use function sprintf;
use function str_contains;
use function str_repeat;
use function str_replace;
use function str_starts_with;
use function strcmp;
use function usort;

/**
 * @author Brian Faust <brian@cline.sh>
 */
trait FormatsResults
{
    /**
     * @return list<string>
     */
    protected function preferredCompetitors(): array
    {
        return ['struct', 'base'];
    }

    /**
     * @return array<string, string>
     */
    protected function competitorAliases(): array
    {
        return [];
    }

    protected function comparisonReference(): string
    {
        return 'closest';
    }

    protected function decimalSeparator(): string
    {
        return '.';
    }

    protected function thousandsSeparator(): string
    {
        return ',';
    }

    protected function rawNumberDecimals(): int
    {
        return 3;
    }

    protected function durationDecimals(): int
    {
        return 3;
    }

    protected function operationsDecimals(): int
    {
        return 0;
    }

    protected function ratioDecimals(): int
    {
        return 2;
    }

    protected function percentageDecimals(): int
    {
        return 1;
    }

    protected function deltaPercentageDecimals(): int
    {
        return 2;
    }

    /**
     * @param array<string, string> $rows
     */
    protected function renderPlainDetailSection(string $title, array $rows, string $accent = 'cyan'): string
    {
        $width = $this->plainSectionWidth($rows);
        $lines = [$this->plainSectionTitle($title, $accent, $width)];

        foreach ($rows as $label => $value) {
            $lines[] = $this->plainDetailLine($label, $value, $width - 2);
        }

        return implode(PHP_EOL, $lines);
    }

    protected function plainSectionTitle(string $title, string $accent = 'cyan', ?int $width = null): string
    {
        return $title;
    }

    protected function plainDetailLine(string $label, string $value, int $width = 52): string
    {
        return sprintf(
            '%s %s %s',
            $label,
            str_repeat('.', max(4, $width - mb_strlen($label) - mb_strlen($value))),
            $value,
        );
    }

    /**
     * @param array<string, string> $rows
     */
    protected function plainSectionWidth(array $rows): int
    {
        $width = $this->defaultPlainSectionWidth();

        foreach ($rows as $label => $value) {
            $width = max($width, mb_strlen($this->plainDetailLine($label, $value)));
        }

        return $width;
    }

    protected function defaultPlainSectionWidth(): int
    {
        return 54;
    }

    /**
     * @param list<BenchmarkResult> $results
     * @param array<string, mixed>  $metadata
     */
    private function asJson(array $results, array $metadata = []): string
    {
        return json_encode([
            'results' => array_map(
                static fn (BenchmarkResult $result): array => $result->toArray(),
                $results,
            ),
            'comparison' => $this->comparisonPayload($results),
            'metadata' => $metadata,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<BenchmarkResult> $results
     */
    private function asCsv(array $results): string
    {
        if ($this->hasMultipleCompetitors($results)) {
            return $this->asComparisonSummaryCsv($results);
        }

        $rows = [[
            'scenario',
            'subject',
            'competitor',
            'parameter_label',
            'groups',
            'median',
            'percentile95',
            'percentile99',
            'operations_per_second',
            'assertions',
        ]];

        foreach ($results as $result) {
            $rows[] = [
                $result->scenario,
                $result->subject,
                $result->competitor,
                $result->parameterLabel(),
                implode('|', $result->groups),
                $this->formatNumber($result->summary->median),
                $this->formatNumber($result->summary->percentile95),
                $this->formatNumber($result->summary->percentile99),
                $this->formatNumber($result->summary->operationsPerSecond),
                $this->assertionSummary($result),
            ];
        }

        return $this->csvDocument($rows);
    }

    /**
     * @param list<BenchmarkResult> $results
     * @param list<BenchmarkResult> $baseline
     * @param array<string, mixed>  $metadata
     */
    private function asComparisonJson(array $results, array $baseline, array $metadata = []): string
    {
        return json_encode([
            'results' => array_map(
                static fn (BenchmarkResult $result): array => $result->toArray(),
                $results,
            ),
            'comparisons' => $this->comparisonRowsAgainstBaseline($results, $baseline),
            'baseline' => [
                'results' => array_map(
                    static fn (BenchmarkResult $result): array => $result->toArray(),
                    $baseline,
                ),
            ],
            'metadata' => $metadata,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<BenchmarkResult> $results
     * @param list<BenchmarkResult> $baseline
     */
    private function asComparisonCsv(array $results, array $baseline): string
    {
        $rows = [[
            'scenario',
            'subject',
            'competitor',
            'parameter_label',
            'current_median',
            'baseline_median',
            'delta_percentage',
            'winner',
            'ratio',
            'percent_faster',
            'significance',
            'regression',
        ]];

        foreach ($this->comparisonRowsAgainstBaseline($results, $baseline) as $row) {
            $rows[] = [
                (string) $row['scenario'],
                (string) $row['subject'],
                (string) $row['competitor'],
                (string) $row['parameter_label'],
                $this->formatNumber((float) $row['current_median']),
                $this->formatNumber((float) $row['baseline_median']),
                $this->formatSignedPercentage((float) $row['delta_percentage']),
                (string) $row['winner'],
                $this->formatRatio((float) $row['ratio']),
                $this->formatPercentage((float) $row['percent_faster']),
                $this->formatSignificance((string) $row['significance']),
                (string) $row['regression_label'],
            ];
        }

        return $this->csvDocument($rows);
    }

    /**
     * @param list<BenchmarkResult> $results
     * @param array<string, mixed>  $metadata
     */
    private function asMarkdown(array $results, array $metadata = []): string
    {
        if ($this->hasMultipleCompetitors($results)) {
            return $this->asComparisonSummaryMarkdown($results, $metadata);
        }

        $comparisonById = [];

        foreach (new ComparisonEngine()->compare($results, $this->comparisonReference())->rows as $row) {
            $comparisonById[$row->result->identifier()] = $row;
        }

        $lines = [
            '| Scenario | Subject | Competitor | Parameters | Groups | Median (ns) | p95 (ns) | p99 (ns) | ops/s | Winner | Ratio | % Faster | Assertions |',
            '| --- | --- | --- | --- | --- | ---: | ---: | ---: | ---: | --- | ---: | ---: | --- |',
        ];

        foreach ($results as $result) {
            $comparison = $comparisonById[$result->identifier()];

            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s | %.3f | %.3f | %.3f | %.3f | %s | %s | %s | %s |',
                $result->scenario,
                $result->subject,
                $this->displayCompetitorLabel($result->competitor),
                $result->parameterLabel(),
                implode(', ', $result->groups),
                $result->summary->median,
                $result->summary->percentile95,
                $result->summary->percentile99,
                $result->summary->operationsPerSecond,
                $comparison->winner,
                $this->formatRatio($comparison->speedRatio),
                $this->formatPercentage($comparison->percentFaster),
                $this->assertionSummary($result),
            );
        }

        return $this->prependMarkdownMetadata(implode(PHP_EOL, $lines), $metadata);
    }

    /**
     * @param list<BenchmarkResult> $results
     * @param list<BenchmarkResult> $baseline
     * @param array<string, mixed>  $metadata
     */
    private function asComparisonMarkdown(array $results, array $baseline, array $metadata = []): string
    {
        $lines = [
            '| Scenario | Subject | Competitor | Parameters | Current Median (ns) | Baseline Median (ns) | Delta % | Winner | Ratio | % Faster | Significance | Regression |',
            '| --- | --- | --- | --- | ---: | ---: | ---: | --- | ---: | ---: | --- | --- |',
        ];

        foreach ($this->comparisonRowsAgainstBaseline($results, $baseline) as $row) {
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %.3f | %.3f | %+.2f | %s | %s | %s | %s | %s |',
                $row['scenario'],
                $row['subject'],
                $this->displayCompetitorLabel((string) $row['competitor']),
                $row['parameter_label'],
                $row['current_median'],
                $row['baseline_median'],
                $row['delta_percentage'],
                $row['winner'],
                $this->formatRatio((float) $row['ratio']),
                $this->formatPercentage((float) $row['percent_faster']),
                $this->formatSignificance((string) $row['significance']),
                $row['regression_label'],
            );
        }

        return $this->prependMarkdownMetadata(implode(PHP_EOL, $lines), $metadata);
    }

    /**
     * @param list<BenchmarkResult> $results
     * @param array<string, mixed>  $metadata
     */
    private function asTable(array $results, array $metadata = []): string
    {
        if ($this->hasMultipleCompetitors($results)) {
            return $this->asComparisonSummaryTable($results, $metadata);
        }

        $comparisonById = [];

        foreach (new ComparisonEngine()->compare($results, $this->comparisonReference())->rows as $row) {
            $comparisonById[$row->result->identifier()] = $row;
        }

        $rows = [];

        foreach ($results as $result) {
            $comparison = $comparisonById[$result->identifier()];

            $rows[] = $this->tableRow(
                $result->scenario,
                $result->subject,
                $this->displayCompetitorLabel($result->competitor),
                $result->parameterLabel(),
                $this->formatNumber($result->summary->median),
                $this->formatNumber($result->summary->percentile95),
                $this->formatNumber($result->summary->percentile99),
                $this->formatNumber($result->summary->operationsPerSecond),
                $comparison->winner,
                $this->formatRatio($comparison->speedRatio),
                $this->formatPercentage($comparison->percentFaster),
                $this->assertionSummary($result),
            );
        }

        return $this->prependPlainMetadata($this->renderConsoleTable(
            headers: ['Scenario', 'Subject', 'Competitor', 'Parameters', 'Median (ns)', 'p95 (ns)', 'p99 (ns)', 'ops/s', 'Winner', 'Ratio', '% Faster', 'Assertions'],
            rows: $rows,
            rightAlignedHeaders: ['Median (ns)', 'p95 (ns)', 'p99 (ns)', 'ops/s', 'Ratio', '% Faster'],
        ), $metadata);
    }

    /**
     * @param list<BenchmarkResult> $results
     * @param list<BenchmarkResult> $baseline
     * @param array<string, mixed>  $metadata
     */
    private function asComparisonTable(array $results, array $baseline, array $metadata = []): string
    {
        $rows = [];

        foreach ($this->comparisonRowsAgainstBaseline($results, $baseline) as $row) {
            $rows[] = $this->tableRow(
                (string) $row['scenario'],
                (string) $row['subject'],
                $this->displayCompetitorLabel((string) $row['competitor']),
                (string) $row['parameter_label'],
                $this->formatNumber((float) $row['current_median']),
                $this->formatNumber((float) $row['baseline_median']),
                $this->formatSignedPercentage((float) $row['delta_percentage']),
                (string) $row['winner'],
                $this->formatRatio((float) $row['ratio']),
                $this->formatPercentage((float) $row['percent_faster']),
                $this->formatSignificance((string) $row['significance']),
                (string) $row['regression_label'],
            );
        }

        return $this->prependPlainMetadata($this->renderConsoleTable(
            headers: ['Scenario', 'Subject', 'Competitor', 'Parameters', 'Current (ns)', 'Baseline (ns)', 'Delta %', 'Winner', 'Ratio', '% Faster', 'Significance', 'Regression'],
            rows: $rows,
            rightAlignedHeaders: ['Current (ns)', 'Baseline (ns)', 'Delta %', 'Ratio', '% Faster'],
        ), $metadata);
    }

    /**
     * @param list<BenchmarkResult> $results
     * @param array<string, mixed>  $metadata
     */
    private function asComparisonSummaryTable(array $results, array $metadata = []): string
    {
        $competitors = $this->competitorsInDisplayOrder($results);
        $summary = $this->comparisonSummary($results, $competitors);
        $headers = $this->comparisonSummaryHeaders($competitors);
        $tables = [];

        foreach ($summary['scenarios'] as $scenario => $rows) {
            $tables[] = implode(PHP_EOL, [
                $this->plainSectionTitle($this->humanizeLabel($scenario), 'cyan'),
                $this->renderConsoleTable(
                    headers: $headers,
                    rows: $rows,
                    rightAlignedHeaders: array_values(array_filter(
                        $headers,
                        static fn (string $header): bool => $header !== 'Benchmark' && $header !== 'Winner',
                    )),
                ),
            ]);
        }

        $overall = [];

        foreach ($summary['winner_counts'] as $competitor => $wins) {
            $overall[$this->displayCompetitorLabel($competitor).' wins'] = sprintf('%d/%d benchmarks', $wins, $summary['benchmark_count']);
        }

        foreach ($summary['average_gaps'] as $competitor => $averageGap) {
            if ($averageGap <= 0.0) {
                continue;
            }

            $overall[$this->displayCompetitorLabel($competitor).' average gap'] = sprintf(
                '%s slower than fastest',
                $this->formatPercentage($averageGap),
            );
        }

        $overall['Geometric mean spread'] = $this->formatRatio($this->geometricMean($summary['ratios']));

        return $this->prependPlainMetadata(implode(PHP_EOL.PHP_EOL, [
            $this->renderPlainDetailSection('Overall', $overall, 'amber'),
            implode(PHP_EOL.PHP_EOL, $tables),
        ]), $metadata);
    }

    /**
     * @param list<BenchmarkResult> $results
     * @param array<string, mixed>  $metadata
     */
    private function asComparisonSummaryMarkdown(array $results, array $metadata = []): string
    {
        $competitors = $this->competitorsInDisplayOrder($results);
        $summary = $this->comparisonSummary($results, $competitors);
        $lines = ['## Comparison'];

        foreach ($summary['scenarios'] as $scenario => $rows) {
            $headers = $this->comparisonSummaryHeaders($competitors);

            $lines[] = '';
            $lines[] = sprintf('### %s', $this->humanizeLabel($scenario));
            $lines[] = '| '.implode(' | ', $headers).' |';
            $lines[] = '| '.implode(' | ', array_map(
                static fn (string $header): string => $header === 'Benchmark' || $header === 'Winner' ? '---' : '---:',
                $headers,
            )).' |';

            foreach ($rows as $row) {
                $lines[] = '| '.implode(' | ', $row).' |';
            }
        }

        $lines[] = '';
        $lines[] = '## Overall';

        foreach ($summary['winner_counts'] as $competitor => $wins) {
            $lines[] = sprintf('- %s wins %d/%d benchmarks.', $this->displayCompetitorLabel($competitor), $wins, $summary['benchmark_count']);
        }

        foreach ($summary['average_gaps'] as $competitor => $averageGap) {
            if ($averageGap <= 0.0) {
                continue;
            }

            $lines[] = sprintf('- %s average gap: %s slower than fastest.', $this->displayCompetitorLabel($competitor), $this->formatPercentage($averageGap));
        }

        $lines[] = sprintf('- Geometric mean spread: %s.', $this->formatRatio($this->geometricMean($summary['ratios'])));

        return $this->prependMarkdownMetadata(implode(PHP_EOL, $lines), $metadata);
    }

    /**
     * @param list<BenchmarkResult> $results
     */
    private function asComparisonSummaryCsv(array $results): string
    {
        $competitors = $this->competitorsInDisplayOrder($results);
        $summary = $this->comparisonSummary($results, $competitors);
        $headers = ['scenario', 'benchmark'];

        foreach ($competitors as $competitor) {
            $headers[] = $competitor;
        }

        $headers[] = 'winner';
        $headers[] = 'ratio';
        $headers[] = 'percent_faster';

        foreach ($competitors as $competitor) {
            $headers[] = sprintf('%s_ops_per_second', $competitor);
        }

        $rows = [$headers];

        foreach ($summary['scenarios'] as $scenario => $scenarioRows) {
            foreach ($scenarioRows as $row) {
                $rows[] = [
                    $scenario,
                    ...$row,
                ];
            }
        }

        return $this->csvDocument($rows);
    }

    /**
     * @param  list<BenchmarkResult>                                                      $results
     * @return array{rows: list<array<string, mixed>>, geometric_mean_speed_ratio: float}
     */
    private function comparisonPayload(array $results): array
    {
        $comparison = new ComparisonEngine()->compare($results, $this->comparisonReference());

        return [
            'rows' => array_map(
                fn (ComparisonRow $row): array => [
                    'scenario' => $row->result->scenario,
                    'subject' => $row->result->subject,
                    'competitor' => $row->result->competitor,
                    'parameters' => $row->result->parameters,
                    'winner' => $row->winner,
                    'delta_percentage' => $row->deltaPercentage,
                    'speed_ratio' => $row->speedRatio,
                    'percent_faster' => $row->percentFaster,
                    'significance' => $this->formatSignificance($row->significance ?? 'n/a'),
                ],
                $comparison->rows,
            ),
            'geometric_mean_speed_ratio' => $comparison->geometricMeanSpeedRatio,
        ];
    }

    /**
     * @param  list<BenchmarkResult>             $results
     * @param  list<BenchmarkResult>             $baseline
     * @return list<array<string, float|string>>
     */
    private function comparisonRowsAgainstBaseline(array $results, array $baseline): array
    {
        $baselineById = [];

        foreach ($baseline as $result) {
            $baselineById[$result->identifier()] = $result;
        }

        $rows = [];
        $evaluator = new RegressionEvaluator();
        $significance = new SignificanceCalculator();

        foreach ($results as $result) {
            $baselineResult = $baselineById[$result->identifier()] ?? null;

            if ($baselineResult === null) {
                continue;
            }

            $metric = $result->regressionMetric ?? 'median';
            $tolerance = $result->regressionTolerance ?? 'n/a';
            $decision = $evaluator->evaluate(
                current: $result,
                baseline: $baselineResult,
                tolerance: $result->regressionTolerance ?? '100%',
                metric: $metric,
            );

            $rows[] = [
                'scenario' => $result->scenario,
                'subject' => $result->subject,
                'competitor' => $result->competitor,
                'parameter_label' => $result->parameterLabel(),
                'current_median' => $result->summary->median,
                'baseline_median' => $baselineResult->summary->median,
                'delta_percentage' => $decision->deltaPercentage,
                'winner' => $this->baselineWinner($result, $baselineResult),
                'ratio' => $this->baselineRatio($result, $baselineResult),
                'percent_faster' => $this->baselinePercentFaster($result, $baselineResult),
                'significance' => $this->formatSignificance($significance->compare($baselineResult->samples, $result->samples)),
                'regression_label' => sprintf('%s @ %s', $metric, $tolerance),
            ];
        }

        return $rows;
    }

    private function assertionSummary(BenchmarkResult $result): string
    {
        if ($result->assertions === []) {
            return 'n/a';
        }

        $passed = 0;

        foreach ($result->assertions as $assertion) {
            if (!$assertion->passed) {
                continue;
            }

            ++$passed;
        }

        return sprintf('%d/%d', $passed, count($result->assertions));
    }

    private function baselineWinner(BenchmarkResult $current, BenchmarkResult $baseline): string
    {
        if ($current->summary->median === $baseline->summary->median) {
            return 'tie';
        }

        return $current->summary->median < $baseline->summary->median ? 'current' : 'baseline';
    }

    private function baselineRatio(BenchmarkResult $current, BenchmarkResult $baseline): float
    {
        $fastest = min($current->summary->median, $baseline->summary->median);
        $slowest = max($current->summary->median, $baseline->summary->median);

        return $slowest / max($fastest, 1.0);
    }

    private function baselinePercentFaster(BenchmarkResult $current, BenchmarkResult $baseline): float
    {
        if ($current->summary->median === $baseline->summary->median) {
            return 0.0;
        }

        $fastest = min($current->summary->median, $baseline->summary->median);
        $slowest = max($current->summary->median, $baseline->summary->median);

        return (($slowest - $fastest) / $slowest) * 100;
    }

    /**
     * @param list<string>       $headers
     * @param list<list<string>> $rows
     * @param list<string>       $rightAlignedHeaders
     */
    private function renderConsoleTable(array $headers, array $rows, array $rightAlignedHeaders = []): string
    {
        $output = new BufferedOutput();
        $table = new Table($output);
        $rightAligned = array_fill_keys($rightAlignedHeaders, true);

        foreach ($headers as $index => $header) {
            if (!isset($rightAligned[$header])) {
                continue;
            }

            $table->setColumnStyle($index, $table->getColumnStyle($index)->setPadType(STR_PAD_LEFT));
        }

        $table
            ->setStyle('box')
            ->setHeaders($headers)
            ->setRows($rows)
            ->render();

        return mb_rtrim($output->fetch());
    }

    /**
     * @return list<string>
     */
    private function tableRow(string ...$columns): array
    {
        return array_values($columns);
    }

    private function formatNumber(float $value): string
    {
        return $this->formatLocalized($value, $this->rawNumberDecimals());
    }

    private function formatRatio(float $value): string
    {
        return $this->formatLocalized($value, $this->ratioDecimals()).'x';
    }

    private function formatPercentage(float $value): string
    {
        return $this->formatLocalized($value, $this->percentageDecimals()).'%';
    }

    private function formatSignedPercentage(float $value): string
    {
        $prefix = $value > 0 ? '+' : '';

        return $prefix.$this->formatLocalized($value, $this->deltaPercentageDecimals()).'%';
    }

    private function formatSignificance(string $value): string
    {
        return match (true) {
            $value === 'winner' => 'fastest',
            str_starts_with($value, 'ns ') => str_replace('ns ', 'not significant ', $value),
            default => $value,
        };
    }

    /**
     * @param list<BenchmarkResult> $results
     * @param list<string>          $competitors
     * @return array{
     *     scenarios: array<string, list<list<string>>>,
     *     winner_counts: array<string, int>,
     *     average_gaps: array<string, float>,
     *     ratios: list<float>,
     *     benchmark_count: int
     * }
     */
    private function comparisonSummary(array $results, array $competitors): array
    {
        $grouped = [];

        foreach ($results as $result) {
            $grouped[$result->scenario][$result->subject][$result->parameterLabel()][$result->competitor] = $result;
        }

        /** @var array<string, list<list<string>>> $scenarioRows */
        $scenarioRows = [];
        $winnerCounts = array_fill_keys($competitors, 0);
        $gapTotals = array_fill_keys($competitors, 0.0);
        $gapCounts = array_fill_keys($competitors, 0);
        $ratios = [];
        $benchmarkCount = 0;

        foreach ($grouped as $scenario => $scenarioResults) {
            foreach ($scenarioResults as $subject => $subjectResults) {
                foreach ($subjectResults as $parameterLabel => $parameterResults) {
                    $available = [];

                    foreach ($competitors as $competitor) {
                        $result = $parameterResults[$competitor] ?? null;

                        if (!$result instanceof BenchmarkResult) {
                            continue;
                        }

                        $available[] = $result;
                    }

                    if (count($available) < 2) {
                        continue;
                    }

                    ++$benchmarkCount;
                    usort(
                        $available,
                        static fn (BenchmarkResult $left, BenchmarkResult $right): int => $left->summary->median <=> $right->summary->median,
                    );

                    $fastest = $available[0];
                    $reference = $this->comparisonReferenceResult($available);

                    if (array_key_exists($fastest->competitor, $winnerCounts)) {
                        ++$winnerCounts[$fastest->competitor];
                    }

                    $ratio = $reference->summary->median / max($fastest->summary->median, 1.0);
                    $ratios[] = $ratio;
                    $row = [$this->comparisonBenchmarkLabel($subject, $parameterLabel)];

                    foreach ($competitors as $competitor) {
                        $result = $parameterResults[$competitor] ?? null;
                        $row[] = $result instanceof BenchmarkResult ? $this->formatDuration($result->summary->median) : '-';

                        if (!$result instanceof BenchmarkResult) {
                            continue;
                        }

                        if (!array_key_exists($competitor, $gapTotals)) {
                            continue;
                        }

                        if ($result === $fastest) {
                            continue;
                        }

                        $gapTotals[$competitor] += (($result->summary->median - $fastest->summary->median) / $fastest->summary->median) * 100;
                        ++$gapCounts[$competitor];
                    }

                    $row[] = $this->displayCompetitorLabel($fastest->competitor);
                    $row[] = $this->formatRatio($ratio);
                    $row[] = $this->formatPercentage((($reference->summary->median - $fastest->summary->median) / $reference->summary->median) * 100);

                    foreach ($competitors as $competitor) {
                        $result = $parameterResults[$competitor] ?? null;
                        $row[] = $result instanceof BenchmarkResult ? $this->formatOperationsPerSecond($result->summary->operationsPerSecond) : '-';
                    }

                    $rowsForScenario = $scenarioRows[$scenario] ?? [];
                    $rowsForScenario[] = $this->tableRow(...$row);
                    $scenarioRows[$scenario] = $rowsForScenario;
                }
            }
        }

        $averageGaps = [];

        foreach ($competitors as $competitor) {
            $averageGaps[$competitor] = ($gapCounts[$competitor] ?? 0) > 0
                ? ($gapTotals[$competitor] / $gapCounts[$competitor])
                : 0.0;
        }

        return [
            'scenarios' => $scenarioRows,
            'winner_counts' => $winnerCounts,
            'average_gaps' => $averageGaps,
            'ratios' => $ratios,
            'benchmark_count' => $benchmarkCount,
        ];
    }

    /**
     * @param  list<string> $competitors
     * @return list<string>
     */
    private function comparisonSummaryHeaders(array $competitors): array
    {
        $headers = ['Benchmark'];

        foreach ($competitors as $competitor) {
            $headers[] = $this->displayCompetitorLabel($competitor);
        }

        $headers[] = 'Winner';

        if ($this->comparisonReference() === 'slowest') {
            $headers[] = 'Field Spread';
            $headers[] = 'Fastest Gain';
        } else {
            $headers[] = 'Closest Gap';
            $headers[] = 'Closest Gain';
        }

        foreach ($competitors as $competitor) {
            $headers[] = sprintf('%s Ops/s', $this->displayCompetitorLabel($competitor));
        }

        return $headers;
    }

    /**
     * @param  list<BenchmarkResult> $results
     * @return list<string>
     */
    private function competitorsInDisplayOrder(array $results): array
    {
        $competitors = [];

        foreach ($results as $result) {
            if (in_array($result->competitor, $competitors, true)) {
                continue;
            }

            $competitors[] = $result->competitor;
        }

        $preferredCompetitors = $this->preferredCompetitors();

        usort($competitors, strcmp(...));

        usort($competitors, static function (string $left, string $right) use ($preferredCompetitors): int {
            $leftPriority = array_search($left, $preferredCompetitors, true);
            $rightPriority = array_search($right, $preferredCompetitors, true);

            if ($leftPriority === false && $rightPriority === false) {
                return strcmp($left, $right);
            }

            if ($leftPriority === false) {
                return 1;
            }

            if ($rightPriority === false) {
                return -1;
            }

            return $leftPriority <=> $rightPriority;
        });

        return $competitors;
    }

    /**
     * @param list<BenchmarkResult> $results
     */
    private function hasMultipleCompetitors(array $results): bool
    {
        return count($this->competitorsInDisplayOrder($results)) > 1;
    }

    /**
     * @param list<BenchmarkResult> $results
     */
    private function comparisonReferenceResult(array $results): BenchmarkResult
    {
        if ($this->comparisonReference() === 'slowest' || count($results) === 2) {
            return $results[count($results) - 1];
        }

        return $results[1];
    }

    private function humanizeLabel(string $label): string
    {
        return mb_strtoupper($label[0]).str_replace('-', ' ', mb_substr($label, 1));
    }

    private function displayCompetitorLabel(string $competitor): string
    {
        $alias = $this->competitorAliases()[$competitor] ?? null;

        if (is_string($alias) && $alias !== '') {
            return $alias;
        }

        return $this->humanizeLabel($competitor);
    }

    private function comparisonBenchmarkLabel(string $subject, string $parameterLabel): string
    {
        if ($parameterLabel === 'default') {
            return $this->humanizeLabel($subject);
        }

        return sprintf('%s (%s)', $this->humanizeLabel($subject), $parameterLabel);
    }

    private function formatDuration(float $nanoseconds): string
    {
        if ($nanoseconds < 1_000.0) {
            return $this->formatLocalized($nanoseconds, $this->durationDecimals()).'ns';
        }

        if ($nanoseconds < 1_000_000.0) {
            return $this->formatLocalized($nanoseconds / 1_000.0, $this->durationDecimals()).'μs';
        }

        if ($nanoseconds < 1_000_000_000.0) {
            return $this->formatLocalized($nanoseconds / 1_000_000.0, $this->durationDecimals()).'ms';
        }

        return $this->formatLocalized($nanoseconds / 1_000_000_000.0, $this->durationDecimals()).'s';
    }

    private function formatOperationsPerSecond(float $value): string
    {
        return sprintf('%s/s', $this->formatLocalized($value, $this->operationsDecimals()));
    }

    private function formatLocalized(float $value, int $decimals): string
    {
        return number_format(
            $value,
            $decimals,
            $this->decimalSeparator(),
            $this->thousandsSeparator(),
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function prependPlainMetadata(string $content, array $metadata): string
    {
        $sections = $this->plainMetadataSections($metadata);

        if ($sections === []) {
            return $content;
        }

        return implode(PHP_EOL.PHP_EOL, [...$sections, $content]);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function prependMarkdownMetadata(string $content, array $metadata): string
    {
        $sections = $this->markdownMetadataSections($metadata);

        if ($sections === []) {
            return $content;
        }

        return implode(PHP_EOL.PHP_EOL, [...$sections, $content]);
    }

    /**
     * @param  array<string, mixed> $metadata
     * @return list<string>
     */
    private function plainMetadataSections(array $metadata): array
    {
        $sections = [];
        $current = is_array($metadata['current'] ?? null) ? $metadata['current'] : [];
        $baseline = is_array($metadata['baseline'] ?? null) ? $metadata['baseline'] : [];

        if (is_array($metadata['environment'] ?? null)) {
            $environment = $metadata['environment'];
            $settings = is_array($metadata['settings'] ?? null) ? $metadata['settings'] : [];

            $sections[] = $this->renderPlainDetailSection('Environment', [
                'PHP' => $this->stringValue($environment['php_version'] ?? null),
                'SAPI' => $this->stringValue($environment['php_sapi'] ?? null),
                'Platform' => sprintf(
                    '%s %s',
                    $this->stringValue($environment['os_family'] ?? null),
                    $this->stringValue($environment['architecture'] ?? null),
                ),
                'Process Isolation' => $this->boolValue($settings['process_isolation'] ?? false) ? 'enabled' : 'disabled',
            ], 'violet');
        } elseif (is_array($current['environment'] ?? null)) {
            $currentEnvironment = $current['environment'];
            $baselineEnvironment = is_array($baseline['environment'] ?? null)
                ? $baseline['environment']
                : [];

            $sections[] = $this->renderPlainDetailSection('Environment', [
                'Current PHP' => $this->stringValue($currentEnvironment['php_version'] ?? null),
                'Current Platform' => sprintf(
                    '%s %s',
                    $this->stringValue($currentEnvironment['os_family'] ?? null),
                    $this->stringValue($currentEnvironment['architecture'] ?? null),
                ),
                'Baseline PHP' => $this->stringValue($baselineEnvironment['php_version'] ?? null),
                'Baseline' => $this->stringValue($metadata['baseline_name'] ?? null),
            ], 'violet');
        }

        $selection = $this->selectionSectionLines($metadata['selection'] ?? $current['selection'] ?? null);

        if ($selection !== []) {
            $selectionMap = [];

            foreach ($selection as $line) {
                [$label, $value] = explode(': ', (string) $line, 2);
                $selectionMap[$label] = $value;
            }

            $sections[] = $this->renderPlainDetailSection('Selection', $selectionMap, 'slate');
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed> $metadata
     * @return list<string>
     */
    private function markdownMetadataSections(array $metadata): array
    {
        $sections = [];
        $current = is_array($metadata['current'] ?? null) ? $metadata['current'] : [];
        $baseline = is_array($metadata['baseline'] ?? null) ? $metadata['baseline'] : [];

        if (is_array($metadata['environment'] ?? null)) {
            $environment = $metadata['environment'];
            $settings = is_array($metadata['settings'] ?? null) ? $metadata['settings'] : [];

            $sections[] = implode(PHP_EOL, [
                '## Environment',
                sprintf('- PHP: `%s`', $this->stringValue($environment['php_version'] ?? null)),
                sprintf('- SAPI: `%s`', $this->stringValue($environment['php_sapi'] ?? null)),
                sprintf('- Platform: `%s %s`', $this->stringValue($environment['os_family'] ?? null), $this->stringValue($environment['architecture'] ?? null)),
                sprintf('- Process Isolation: `%s`', $this->boolValue($settings['process_isolation'] ?? false) ? 'enabled' : 'disabled'),
            ]);
        } elseif (is_array($current['environment'] ?? null)) {
            $currentEnvironment = $current['environment'];
            $baselineEnvironment = is_array($baseline['environment'] ?? null)
                ? $baseline['environment']
                : [];

            $sections[] = implode(PHP_EOL, [
                '## Environment',
                sprintf('- Current PHP: `%s`', $this->stringValue($currentEnvironment['php_version'] ?? null)),
                sprintf('- Current Platform: `%s %s`', $this->stringValue($currentEnvironment['os_family'] ?? null), $this->stringValue($currentEnvironment['architecture'] ?? null)),
                sprintf('- Baseline PHP: `%s`', $this->stringValue($baselineEnvironment['php_version'] ?? null)),
                sprintf('- Baseline: `%s`', $this->stringValue($metadata['baseline_name'] ?? null)),
            ]);
        }

        $selection = $this->selectionSectionLines($metadata['selection'] ?? $current['selection'] ?? null);

        if ($selection !== []) {
            $sections[] = implode(PHP_EOL, [
                '## Selection',
                ...array_map(
                    static fn (string $line): string => '- '.$line,
                    $selection,
                ),
            ]);
        }

        return $sections;
    }

    /**
     * @return list<string>
     */
    private function selectionSectionLines(mixed $selection): array
    {
        if (!is_array($selection)) {
            return [];
        }

        $lines = [];
        $filter = $selection['filter'] ?? null;
        $groups = is_array($selection['groups'] ?? null) ? $selection['groups'] : [];
        $competitors = is_array($selection['competitors'] ?? null) ? $selection['competitors'] : [];

        if (is_string($filter) && $filter !== '') {
            $lines[] = sprintf('Filter: `%s`', $filter);
        }

        if ($groups !== []) {
            $lines[] = sprintf('Groups: `%s`', implode('`, `', array_map($this->stringValue(...), $groups)));
        }

        if ($competitors !== []) {
            $lines[] = sprintf('Competitors: `%s`', implode('`, `', array_map($this->stringValue(...), $competitors)));
        }

        return $lines;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function boolValue(mixed $value): bool
    {
        return is_bool($value) && $value;
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

    /**
     * @param list<list<string>> $rows
     */
    private function csvDocument(array $rows): string
    {
        return implode(PHP_EOL, array_map(
            fn (array $row): string => implode(',', array_map($this->csvField(...), $row)),
            $rows,
        ));
    }

    private function csvField(string $value): string
    {
        $escaped = str_replace('"', '""', $value);

        if (str_contains($escaped, ',') || str_contains($escaped, '"') || str_contains($escaped, PHP_EOL)) {
            return sprintf('"%s"', $escaped);
        }

        return $escaped;
    }
}
