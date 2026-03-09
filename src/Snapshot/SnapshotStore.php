<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Snapshot;

use Cline\Bench\Enums\AssertionOperator;
use Cline\Bench\Enums\Metric;
use Cline\Bench\Execution\AssertionResult;
use Cline\Bench\Execution\BenchmarkResult;
use Cline\Bench\Statistics\SummaryStatistics;
use RuntimeException;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

use function array_filter;
use function array_map;
use function array_values;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function mb_rtrim;
use function mkdir;
use function sprintf;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class SnapshotStore
{
    public function __construct(
        private string $directory,
    ) {}

    /**
     * @param list<BenchmarkResult> $results
     * @param array<string, mixed>  $metadata
     */
    public function save(string $name, array $results, array $metadata = []): void
    {
        if (!is_dir($this->directory) && !mkdir($concurrentDirectory = $this->directory, 0o755, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Unable to create snapshot directory [%s].', $this->directory));
        }

        $payload = [
            'name' => $name,
            'metadata' => $metadata,
            'results' => array_map(
                static fn (BenchmarkResult $result): array => $result->toArray(),
                $results,
            ),
        ];

        file_put_contents($this->path($name), json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        if ($name === 'latest') {
            return;
        }

        file_put_contents($this->path('latest'), json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    public function load(string $name): Snapshot
    {
        $contents = file_get_contents($this->path($name));

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to load snapshot [%s].', $name));
        }

        /** @var array{name: string, metadata: array<string, mixed>, results: list<array<string, mixed>>} $payload */
        $payload = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        return new Snapshot(
            name: $payload['name'],
            results: array_map(
                $this->hydrateResult(...),
                $payload['results'],
            ),
            metadata: $payload['metadata'],
        );
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrateResult(array $result): BenchmarkResult
    {
        /** @var array<string, mixed> $summary */
        $summary = is_array($result['summary'] ?? null) ? $result['summary'] : [];

        /** @var array<string, mixed> $parameters */
        $parameters = is_array($result['parameters'] ?? null) ? $result['parameters'] : [];

        /** @var list<float> $samples */
        $samples = array_map(
            $this->floatValue(...),
            is_array($result['samples'] ?? null) ? $result['samples'] : [],
        );

        return new BenchmarkResult(
            subject: $this->stringValue($result['subject'] ?? null),
            scenario: $this->stringValue($result['scenario'] ?? null),
            competitor: $this->stringValue($result['competitor'] ?? null),
            summary: new SummaryStatistics(
                samples: $this->intValue($summary['samples'] ?? null),
                min: $this->floatValue($summary['min'] ?? null),
                max: $this->floatValue($summary['max'] ?? null),
                mean: $this->floatValue($summary['mean'] ?? null),
                median: $this->floatValue($summary['median'] ?? null),
                standardDeviation: $this->floatValue($summary['standard_deviation'] ?? null),
                relativeMarginOfError: $this->floatValue($summary['relative_margin_of_error'] ?? null),
                percentile75: $this->floatValue($summary['percentile75'] ?? null),
                percentile95: $this->floatValue($summary['percentile95'] ?? null),
                percentile99: $this->floatValue($summary['percentile99'] ?? null),
                operationsPerSecond: $this->floatValue($summary['operations_per_second'] ?? null),
            ),
            samples: $samples,
            parameters: $parameters,
            groups: array_values(array_filter(
                is_array($result['groups'] ?? null) ? $result['groups'] : [],
                is_string(...),
            )),
            assertions: array_map(
                fn (array $assertion): AssertionResult => new AssertionResult(
                    metric: $this->metricValue($assertion['metric'] ?? null) ?? Metric::Median,
                    operator: $this->assertionOperatorValue($assertion['operator'] ?? null),
                    expected: $this->floatValue($assertion['expected'] ?? null),
                    actual: $this->floatValue($assertion['actual'] ?? null),
                    passed: (bool) ($assertion['passed'] ?? false),
                ),
                array_values(array_filter(
                    is_array($result['assertions'] ?? null) ? $result['assertions'] : [],
                    is_array(...),
                )),
            ),
            regressionMetric: is_array($result['regression'] ?? null)
                ? $this->metricValue($result['regression']['metric'] ?? null)
                : null,
            regressionTolerance: is_array($result['regression'] ?? null)
                ? $this->stringValue($result['regression']['tolerance'] ?? null)
                : null,
        );
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function intValue(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }

    private function floatValue(mixed $value): float
    {
        return is_float($value) || is_int($value) ? (float) $value : 0.0;
    }

    private function metricValue(mixed $value): ?Metric
    {
        return is_string($value) ? Metric::tryFrom($value) : null;
    }

    private function assertionOperatorValue(mixed $value): AssertionOperator
    {
        if (!is_string($value)) {
            return AssertionOperator::Equal;
        }

        return AssertionOperator::tryFrom($value) ?? AssertionOperator::Equal;
    }

    private function path(string $name): string
    {
        return mb_rtrim($this->directory, '/').'/'.$name.'.json';
    }
}
