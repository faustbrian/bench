<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Execution;

use Cline\Bench\Configuration\BenchConfig;
use Cline\Bench\Discovery\BenchmarkAssertion;
use Cline\Bench\Discovery\BenchmarkDiscovery;
use Cline\Bench\Discovery\DiscoveredBenchmark;
use Cline\Bench\Statistics\SummaryStatistics;
use Cline\Bench\Statistics\SummaryStatisticsCalculator;
use RuntimeException;

use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;

use function ceil;
use function count;
use function dirname;
use function escapeshellarg;
use function file_exists;
use function getcwd;
use function hrtime;
use function is_string;
use function json_decode;
use function json_encode;
use function max;
use function mb_trim;
use function shell_exec;
use function sprintf;
use function throw_if;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class BenchmarkRunner
{
    public function __construct(
        private BenchmarkDiscovery $discovery = new BenchmarkDiscovery(),
        private SummaryStatisticsCalculator $statistics = new SummaryStatisticsCalculator(),
    ) {}

    /**
     * @param  null|callable(string, int, int, string, string, string, ?BenchmarkResult): void $onProgress
     * @return list<BenchmarkResult>
     */
    public function runPath(string $path, ?BenchConfig $config = null, ?callable $onProgress = null, ?BenchmarkSelection $selection = null): array
    {
        $config ??= BenchConfig::default();
        $results = [];
        $executions = [];

        foreach ($this->discovery->discover(
            $path,
            $config->defaultIterations,
            $config->defaultRevolutions,
            $config->defaultWarmupIterations,
        ) as $benchmark) {
            if ($selection instanceof BenchmarkSelection && !$selection->matchesDiscoveredBenchmark($benchmark)) {
                continue;
            }

            $parameterSets = $benchmark->parameterSets === [] ? [[]] : $benchmark->parameterSets;

            foreach ($parameterSets as $parameters) {
                $executions[] = [$benchmark, $parameters];
            }
        }

        $total = count($executions);

        foreach ($executions as $position => [$benchmark, $parameters]) {
            if ($onProgress !== null) {
                $onProgress(
                    'running',
                    $position + 1,
                    $total,
                    $benchmark->scenario,
                    $benchmark->subject,
                    $benchmark->competitor,
                    null,
                );
            }

            $result = $this->runBenchmark($benchmark, $parameters, $config);
            $results[] = $result;

            if ($onProgress === null) {
                continue;
            }

            $onProgress(
                'completed',
                $position + 1,
                $total,
                $benchmark->scenario,
                $benchmark->subject,
                $benchmark->competitor,
                $result,
            );
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function runBenchmark(DiscoveredBenchmark $benchmark, array $parameters, BenchConfig $config): BenchmarkResult
    {
        $revolutions = $this->calibratedRevolutions($benchmark, $parameters, $config);

        if ($config->processIsolation) {
            return $this->runIsolatedBenchmark($benchmark, $parameters, $revolutions);
        }

        for ($iteration = 0; $iteration < $benchmark->warmupIterations; ++$iteration) {
            $this->localSample($benchmark, $parameters, $revolutions);
        }

        $samples = [];

        for ($iteration = 0; $iteration < $benchmark->iterations; ++$iteration) {
            $samples[] = $this->localSample($benchmark, $parameters, $revolutions) / $revolutions;
        }

        $summary = $this->statistics->summarize($samples);

        return new BenchmarkResult(
            subject: $benchmark->subject,
            scenario: $benchmark->scenario,
            competitor: $benchmark->competitor,
            summary: $summary,
            samples: $samples,
            parameters: $parameters,
            groups: $benchmark->groups,
            assertions: $this->evaluateAssertions($benchmark->assertions, $summary),
            regressionMetric: $benchmark->regressionMetric !== '' ? $benchmark->regressionMetric : null,
            regressionTolerance: $benchmark->regressionTolerance !== '' ? $benchmark->regressionTolerance : null,
        );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function calibratedRevolutions(DiscoveredBenchmark $benchmark, array $parameters, BenchConfig $config): int
    {
        if ($config->calibrationBudgetNanoseconds <= 0) {
            return $benchmark->revolutions;
        }

        $elapsed = $config->processIsolation
            ? $this->isolatedSample($benchmark, $parameters, $benchmark->revolutions)
            : $this->localCalibrationSample($benchmark, $parameters, $benchmark->revolutions);

        if ($elapsed >= $config->calibrationBudgetNanoseconds) {
            return $benchmark->revolutions;
        }

        return max(
            $benchmark->revolutions,
            (int) max(
                1,
                ceil($config->calibrationBudgetNanoseconds / max($elapsed, 1)),
            ) * $benchmark->revolutions,
        );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function localCalibrationSample(DiscoveredBenchmark $benchmark, array $parameters, int $revolutions): float
    {
        $instance = new ($benchmark->className)();

        $this->invokeHooks($instance, $benchmark->beforeMethods);
        $startedAt = hrtime(true);
        $this->invokeSubject($instance, $benchmark->methodName, $revolutions, $parameters);
        $elapsed = hrtime(true) - $startedAt;
        $this->invokeHooks($instance, $benchmark->afterMethods);

        return (float) $elapsed;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function localSample(DiscoveredBenchmark $benchmark, array $parameters, int $revolutions): float
    {
        $instance = new ($benchmark->className)();

        $this->invokeHooks($instance, $benchmark->beforeMethods);
        $startedAt = hrtime(true);
        $this->invokeSubject($instance, $benchmark->methodName, $revolutions, $parameters);
        $elapsed = hrtime(true) - $startedAt;
        $this->invokeHooks($instance, $benchmark->afterMethods);

        return (float) $elapsed;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function runIsolatedBenchmark(DiscoveredBenchmark $benchmark, array $parameters, int $revolutions): BenchmarkResult
    {
        for ($iteration = 0; $iteration < $benchmark->warmupIterations; ++$iteration) {
            $this->isolatedSample($benchmark, $parameters, $revolutions);
        }

        $samples = [];

        for ($iteration = 0; $iteration < $benchmark->iterations; ++$iteration) {
            $samples[] = $this->isolatedSample($benchmark, $parameters, $revolutions) / $revolutions;
        }

        $summary = $this->statistics->summarize($samples);

        return new BenchmarkResult(
            subject: $benchmark->subject,
            scenario: $benchmark->scenario,
            competitor: $benchmark->competitor,
            summary: $summary,
            samples: $samples,
            parameters: $parameters,
            groups: $benchmark->groups,
            assertions: $this->evaluateAssertions($benchmark->assertions, $summary),
            regressionMetric: $benchmark->regressionMetric !== '' ? $benchmark->regressionMetric : null,
            regressionTolerance: $benchmark->regressionTolerance !== '' ? $benchmark->regressionTolerance : null,
        );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function isolatedSample(DiscoveredBenchmark $benchmark, array $parameters, int $revolutions): float
    {
        $payload = json_encode([
            'autoload' => $this->autoloadPath(),
            'source_path' => $benchmark->sourcePath,
            'class_name' => $benchmark->className,
            'method_name' => $benchmark->methodName,
            'before_methods' => $benchmark->beforeMethods,
            'after_methods' => $benchmark->afterMethods,
            'parameters' => $parameters,
            'revolutions' => $revolutions,
        ], JSON_THROW_ON_ERROR);

        $script = <<<'PHP'
$payload = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
require $payload['autoload'];
require_once $payload['source_path'];
$instance = new $payload['class_name']();
foreach ($payload['before_methods'] as $method) {
    $instance->{$method}();
}
$startedAt = hrtime(true);
for ($iteration = 0; $iteration < $payload['revolutions']; ++$iteration) {
    $instance->{$payload['method_name']}(...$payload['parameters']);
}
$elapsed = hrtime(true) - $startedAt;
foreach ($payload['after_methods'] as $method) {
    $instance->{$method}();
}
echo json_encode(['elapsed' => $elapsed], JSON_THROW_ON_ERROR);
PHP;

        $command = sprintf(
            '%s -r %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($script),
            escapeshellarg($payload),
        );

        $output = shell_exec($command);

        throw_if(!is_string($output) || mb_trim($output) === '', RuntimeException::class, 'Unable to execute isolated benchmark process.');

        /** @var string $output */
        /** @var array{elapsed?: float|int} $decoded */
        $decoded = json_decode(mb_trim($output), true, flags: JSON_THROW_ON_ERROR);

        return (float) ($decoded['elapsed'] ?? 0.0);
    }

    private function autoloadPath(): string
    {
        $workingDirectory = getcwd();

        if (is_string($workingDirectory)) {
            $projectAutoload = $workingDirectory.'/vendor/autoload.php';

            if (file_exists($projectAutoload)) {
                return $projectAutoload;
            }
        }

        return dirname(__DIR__, 2).'/vendor/autoload.php';
    }

    /**
     * @param list<string> $methods
     */
    private function invokeHooks(object $instance, array $methods): void
    {
        foreach ($methods as $method) {
            $instance->{$method}();
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function invokeSubject(object $instance, string $method, int $revolutions, array $parameters): void
    {
        for ($revolution = 0; $revolution < $revolutions; ++$revolution) {
            $instance->{$method}(...$parameters);
        }
    }

    /**
     * @param  list<BenchmarkAssertion> $assertions
     * @return list<AssertionResult>
     */
    private function evaluateAssertions(array $assertions, SummaryStatistics $summary): array
    {
        $results = [];

        foreach ($assertions as $assertion) {
            $actual = $this->metricValue($summary, $assertion->metric);

            $results[] = new AssertionResult(
                metric: $assertion->metric,
                operator: $assertion->operator,
                expected: $assertion->value,
                actual: $actual,
                passed: $this->compare($actual, $assertion->operator, $assertion->value),
            );
        }

        return $results;
    }

    private function metricValue(SummaryStatistics $summary, string $metric): float
    {
        return match ($metric) {
            'min' => $summary->min,
            'max' => $summary->max,
            'mean' => $summary->mean,
            'median' => $summary->median,
            'p75', 'percentile75' => $summary->percentile75,
            'p95', 'percentile95' => $summary->percentile95,
            'p99', 'percentile99' => $summary->percentile99,
            'ops/s', 'operations_per_second' => $summary->operationsPerSecond,
            default => $summary->median,
        };
    }

    private function compare(float $actual, string $operator, float $expected): bool
    {
        return match ($operator) {
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
            '>' => $actual > $expected,
            '>=' => $actual >= $expected,
            '=', '==' => $actual === $expected,
            default => false,
        };
    }
}
