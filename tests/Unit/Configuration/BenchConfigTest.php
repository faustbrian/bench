<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Configuration\BenchConfig;
use Cline\Bench\Configuration\BenchConfigLoader;
use Cline\Bench\Enums\ComparisonReference;
use Cline\Bench\Enums\Metric;
use Cline\Bench\Enums\ReportFormat;
use Cline\Bench\Enums\TimeUnit;
use Cline\Bench\Environment\CompatibilityMode;

describe('BenchConfig', function (): void {
    it('provides stable defaults for benchmarks and snapshots', function (): void {
        $config = BenchConfig::default();

        expect($config->benchmarkPath)->toBe('benchmarks')
            ->and($config->snapshotPath)->toBe('.bench/snapshots')
            ->and($config->runPath)->toBe('.bench/runs')
            ->and($config->preferredCompetitors)->toBe(['struct', 'base'])
            ->and($config->competitorAliases)->toBe([])
            ->and($config->scenarioBaselines)->toBe([])
            ->and($config->bootstrapPath)->toBeNull()
            ->and($config->defaultIterations)->toBe(5)
            ->and($config->defaultRevolutions)->toBe(1)
            ->and($config->defaultWarmupIterations)->toBe(0)
            ->and($config->calibrationBudgetNanoseconds)->toBe(0)
            ->and($config->processIsolation)->toBeFalse()
            ->and($config->defaultRegressionMetric)->toBe(Metric::Median)
            ->and($config->defaultRegressionTolerance)->toBe('5%')
            ->and($config->defaultReportFormat)->toBe(ReportFormat::Table)
            ->and($config->progressMetric)->toBe(Metric::Median)
            ->and($config->progressTimeUnit)->toBe(TimeUnit::Microseconds)
            ->and($config->comparisonReference)->toBe(ComparisonReference::Closest)
            ->and($config->decimalSeparator)->toBe('.')
            ->and($config->thousandsSeparator)->toBe(',')
            ->and($config->rawNumberDecimals)->toBe(3)
            ->and($config->durationDecimals)->toBe(3)
            ->and($config->operationsDecimals)->toBe(0)
            ->and($config->progressTimeDecimals)->toBe(3)
            ->and($config->progressOperationsDecimals)->toBe(3)
            ->and($config->ratioDecimals)->toBe(2)
            ->and($config->percentageDecimals)->toBe(1)
            ->and($config->deltaPercentageDecimals)->toBe(2)
            ->and($config->compatibilityMode)->toBe(CompatibilityMode::Warn);
    });

    it('loads a typed config file from the working directory', function (): void {
        $directory = sys_get_temp_dir().'/bench-config-'.bin2hex(random_bytes(8));
        mkdir($directory, 0o755, true);

        file_put_contents($directory.'/bench.php', <<<'PHP'
<?php declare(strict_types=1);

use Cline\Bench\Configuration\BenchConfig;
use Cline\Bench\Environment\CompatibilityMode;
use Cline\Bench\Enums\ComparisonReference;
use Cline\Bench\Enums\Metric;
use Cline\Bench\Enums\ReportFormat;
use Cline\Bench\Enums\TimeUnit;

return BenchConfig::default()
    ->withBenchmarkPath('custom-benchmarks')
    ->withSnapshotPath('.snapshots/bench')
    ->withRunPath('.runs/bench')
    ->withPreferredCompetitors(['valinor', 'struct'])
    ->withCompetitorAliases(['spatie-data' => 'Spatie'])
    ->withScenarioBaselines(['dto-transform' => 'snapshot:transform-baseline'])
    ->withBootstrapPath('bench-bootstrap.php')
    ->withDefaultIterations(9)
    ->withDefaultRevolutions(7)
    ->withDefaultWarmupIterations(3)
    ->withCalibrationBudgetNanoseconds(5000000)
    ->withProcessIsolation(true)
    ->withDefaultRegression(metric: Metric::OperationsPerSecond, tolerance: '3%')
    ->withDefaultReportFormat(ReportFormat::Markdown)
    ->withProgressMetric(Metric::Mean)
    ->withProgressTimeUnit(TimeUnit::Milliseconds)
    ->withComparisonReference(ComparisonReference::Slowest)
    ->withNumberSeparators(decimalSeparator: ',', thousandsSeparator: '.')
    ->withRawNumberDecimals(1)
    ->withDurationDecimals(0)
    ->withOperationsDecimals(2)
    ->withProgressDecimals(timeDecimals: 0, operationsDecimals: 0)
    ->withRatioDecimals(3)
    ->withPercentageDecimals(2, 4)
    ->withCompatibilityMode(CompatibilityMode::Fail);
PHP);

        $config = BenchConfigLoader::load($directory);

        expect($config->benchmarkPath)->toBe('custom-benchmarks')
            ->and($config->snapshotPath)->toBe('.snapshots/bench')
            ->and($config->runPath)->toBe('.runs/bench')
            ->and($config->preferredCompetitors)->toBe(['valinor', 'struct'])
            ->and($config->competitorAliases)->toBe(['spatie-data' => 'Spatie'])
            ->and($config->scenarioBaselines)->toBe(['dto-transform' => 'snapshot:transform-baseline'])
            ->and($config->bootstrapPath)->toBe('bench-bootstrap.php')
            ->and($config->defaultIterations)->toBe(9)
            ->and($config->defaultRevolutions)->toBe(7)
            ->and($config->defaultWarmupIterations)->toBe(3)
            ->and($config->calibrationBudgetNanoseconds)->toBe(5_000_000)
            ->and($config->processIsolation)->toBeTrue()
            ->and($config->defaultRegressionMetric)->toBe(Metric::OperationsPerSecond)
            ->and($config->defaultRegressionTolerance)->toBe('3%')
            ->and($config->defaultReportFormat)->toBe(ReportFormat::Markdown)
            ->and($config->progressMetric)->toBe(Metric::Mean)
            ->and($config->progressTimeUnit)->toBe(TimeUnit::Milliseconds)
            ->and($config->comparisonReference)->toBe(ComparisonReference::Slowest)
            ->and($config->decimalSeparator)->toBe(',')
            ->and($config->thousandsSeparator)->toBe('.')
            ->and($config->rawNumberDecimals)->toBe(1)
            ->and($config->durationDecimals)->toBe(0)
            ->and($config->operationsDecimals)->toBe(2)
            ->and($config->progressTimeDecimals)->toBe(0)
            ->and($config->progressOperationsDecimals)->toBe(0)
            ->and($config->ratioDecimals)->toBe(3)
            ->and($config->percentageDecimals)->toBe(2)
            ->and($config->deltaPercentageDecimals)->toBe(4)
            ->and($config->compatibilityMode)->toBe(CompatibilityMode::Fail);
    });
});
