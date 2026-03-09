<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Configuration;

use Cline\Bench\Enums\ComparisonReference;
use Cline\Bench\Enums\Metric;
use Cline\Bench\Enums\ReportFormat;
use Cline\Bench\Enums\TimeUnit;
use Cline\Bench\Environment\CompatibilityMode;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class BenchConfig
{
    public function __construct(
        public string $benchmarkPath = 'benchmarks',
        public string $snapshotPath = '.bench/snapshots',
        public string $runPath = '.bench/runs',
        /** @var list<string> */
        public array $preferredCompetitors = ['struct', 'base'],
        /** @var array<string, string> */
        public array $competitorAliases = [],
        /** @var array<string, string> */
        public array $scenarioReferences = [],
        public ?string $bootstrapPath = null,
        public int $defaultIterations = 5,
        public int $defaultRevolutions = 1,
        public int $defaultWarmupIterations = 0,
        public int $calibrationBudgetNanoseconds = 0,
        public bool $processIsolation = false,
        public Metric $defaultRegressionMetric = Metric::Median,
        public string $defaultRegressionTolerance = '5%',
        public ReportFormat $defaultReportFormat = ReportFormat::Table,
        public Metric $progressMetric = Metric::Median,
        public TimeUnit $progressTimeUnit = TimeUnit::Microseconds,
        public ComparisonReference $comparisonReference = ComparisonReference::Closest,
        public string $decimalSeparator = '.',
        public string $thousandsSeparator = ',',
        public int $rawNumberDecimals = 3,
        public int $durationDecimals = 3,
        public int $operationsDecimals = 0,
        public int $progressTimeDecimals = 3,
        public int $progressOperationsDecimals = 3,
        public int $ratioDecimals = 2,
        public int $percentageDecimals = 1,
        public int $deltaPercentageDecimals = 2,
        public bool $significanceEnabled = true,
        public float $significanceAlpha = 0.05,
        public int $significanceMinimumSamples = 2,
        public CompatibilityMode $compatibilityMode = CompatibilityMode::Warn,
    ) {}

    public static function default(): self
    {
        return new self();
    }

    public function storage(): StorageConfig
    {
        return new StorageConfig(
            benchmarkPath: $this->benchmarkPath,
            snapshotPath: $this->snapshotPath,
            runPath: $this->runPath,
            bootstrapPath: $this->bootstrapPath,
        );
    }

    public function execution(): ExecutionConfig
    {
        return new ExecutionConfig(
            defaultIterations: $this->defaultIterations,
            defaultRevolutions: $this->defaultRevolutions,
            defaultWarmupIterations: $this->defaultWarmupIterations,
            calibrationBudgetNanoseconds: $this->calibrationBudgetNanoseconds,
            processIsolation: $this->processIsolation,
            defaultRegressionMetric: $this->defaultRegressionMetric,
            defaultRegressionTolerance: $this->defaultRegressionTolerance,
            compatibilityMode: $this->compatibilityMode,
        );
    }

    public function reporting(): ReportingConfig
    {
        return new ReportingConfig(
            defaultReportFormat: $this->defaultReportFormat,
            progressMetric: $this->progressMetric,
            progressTimeUnit: $this->progressTimeUnit,
            decimalSeparator: $this->decimalSeparator,
            thousandsSeparator: $this->thousandsSeparator,
            rawNumberDecimals: $this->rawNumberDecimals,
            durationDecimals: $this->durationDecimals,
            operationsDecimals: $this->operationsDecimals,
            progressTimeDecimals: $this->progressTimeDecimals,
            progressOperationsDecimals: $this->progressOperationsDecimals,
            ratioDecimals: $this->ratioDecimals,
            percentageDecimals: $this->percentageDecimals,
            deltaPercentageDecimals: $this->deltaPercentageDecimals,
        );
    }

    public function comparison(): ComparisonConfig
    {
        return new ComparisonConfig(
            preferredCompetitors: $this->preferredCompetitors,
            competitorAliases: $this->competitorAliases,
            scenarioReferences: $this->scenarioReferences,
            comparisonReference: $this->comparisonReference,
            significanceEnabled: $this->significanceEnabled,
            significanceAlpha: $this->significanceAlpha,
            significanceMinimumSamples: $this->significanceMinimumSamples,
        );
    }

    public function withBenchmarkPath(string $benchmarkPath): self
    {
        return $this->copy(benchmarkPath: $benchmarkPath);
    }

    public function withSnapshotPath(string $snapshotPath): self
    {
        return $this->copy(snapshotPath: $snapshotPath);
    }

    public function withRunPath(string $runPath): self
    {
        return $this->copy(runPath: $runPath);
    }

    public function withBootstrapPath(?string $bootstrapPath): self
    {
        return $this->copy(
            bootstrapPath: $bootstrapPath,
            bootstrapPathIsSet: true,
        );
    }

    public function withDefaultIterations(int $defaultIterations): self
    {
        return $this->copy(defaultIterations: $defaultIterations);
    }

    public function withDefaultRevolutions(int $defaultRevolutions): self
    {
        return $this->copy(defaultRevolutions: $defaultRevolutions);
    }

    public function withDefaultWarmupIterations(int $defaultWarmupIterations): self
    {
        return $this->copy(defaultWarmupIterations: $defaultWarmupIterations);
    }

    public function withCalibrationBudgetNanoseconds(int $calibrationBudgetNanoseconds): self
    {
        return $this->copy(calibrationBudgetNanoseconds: $calibrationBudgetNanoseconds);
    }

    public function withProcessIsolation(bool $processIsolation): self
    {
        return $this->copy(
            processIsolation: $processIsolation,
            processIsolationIsSet: true,
        );
    }

    public function withDefaultRegression(Metric $metric, string $tolerance): self
    {
        return $this->copy(
            defaultRegressionMetric: $metric,
            defaultRegressionTolerance: $tolerance,
        );
    }

    public function withDefaultReportFormat(ReportFormat $defaultReportFormat): self
    {
        return $this->copy(defaultReportFormat: $defaultReportFormat);
    }

    /**
     * @param list<string> $preferredCompetitors
     */
    public function withPreferredCompetitors(array $preferredCompetitors): self
    {
        return $this->copy(preferredCompetitors: $preferredCompetitors);
    }

    /**
     * @param array<string, string> $competitorAliases
     */
    public function withCompetitorAliases(array $competitorAliases): self
    {
        return $this->copy(competitorAliases: $competitorAliases);
    }

    /**
     * @param array<string, string> $scenarioReferences
     */
    public function withScenarioReferences(array $scenarioReferences): self
    {
        return $this->copy(scenarioReferences: $scenarioReferences);
    }

    public function withProgressMetric(Metric $progressMetric): self
    {
        return $this->copy(progressMetric: $progressMetric);
    }

    public function withProgressTimeUnit(TimeUnit $progressTimeUnit): self
    {
        return $this->copy(progressTimeUnit: $progressTimeUnit);
    }

    public function withComparisonReference(ComparisonReference $comparisonReference): self
    {
        return $this->copy(comparisonReference: $comparisonReference);
    }

    public function withNumberSeparators(string $decimalSeparator, string $thousandsSeparator): self
    {
        return $this->copy(
            decimalSeparator: $decimalSeparator,
            thousandsSeparator: $thousandsSeparator,
        );
    }

    public function withRawNumberDecimals(int $rawNumberDecimals): self
    {
        return $this->copy(rawNumberDecimals: $rawNumberDecimals);
    }

    public function withDurationDecimals(int $durationDecimals): self
    {
        return $this->copy(durationDecimals: $durationDecimals);
    }

    public function withOperationsDecimals(int $operationsDecimals): self
    {
        return $this->copy(operationsDecimals: $operationsDecimals);
    }

    public function withProgressDecimals(int $timeDecimals, ?int $operationsDecimals = null): self
    {
        return $this->copy(
            progressTimeDecimals: $timeDecimals,
            progressOperationsDecimals: $operationsDecimals ?? $timeDecimals,
        );
    }

    public function withRatioDecimals(int $ratioDecimals): self
    {
        return $this->copy(ratioDecimals: $ratioDecimals);
    }

    public function withPercentageDecimals(int $percentageDecimals, ?int $deltaPercentageDecimals = null): self
    {
        return $this->copy(
            percentageDecimals: $percentageDecimals,
            deltaPercentageDecimals: $deltaPercentageDecimals ?? $percentageDecimals,
        );
    }

    public function withSignificance(float $alpha = 0.05, int $minimumSamples = 2): self
    {
        return $this->copy(
            significanceEnabled: true,
            significanceEnabledIsSet: true,
            significanceAlpha: $alpha,
            significanceMinimumSamples: $minimumSamples,
        );
    }

    public function withoutSignificance(): self
    {
        return $this->copy(
            significanceEnabled: false,
            significanceEnabledIsSet: true,
        );
    }

    public function withCompatibilityMode(CompatibilityMode $compatibilityMode): self
    {
        return $this->copy(compatibilityMode: $compatibilityMode);
    }

    /**
     * @param null|list<string>          $preferredCompetitors
     * @param null|array<string, string> $competitorAliases
     * @param null|array<string, string> $scenarioReferences
     */
    private function copy(
        ?string $benchmarkPath = null,
        ?string $snapshotPath = null,
        ?string $runPath = null,
        ?array $preferredCompetitors = null,
        ?array $competitorAliases = null,
        ?array $scenarioReferences = null,
        ?string $bootstrapPath = null,
        bool $bootstrapPathIsSet = false,
        ?int $defaultIterations = null,
        ?int $defaultRevolutions = null,
        ?int $defaultWarmupIterations = null,
        ?int $calibrationBudgetNanoseconds = null,
        bool $processIsolation = false,
        bool $processIsolationIsSet = false,
        ?Metric $defaultRegressionMetric = null,
        ?string $defaultRegressionTolerance = null,
        ?ReportFormat $defaultReportFormat = null,
        ?Metric $progressMetric = null,
        ?TimeUnit $progressTimeUnit = null,
        ?ComparisonReference $comparisonReference = null,
        ?string $decimalSeparator = null,
        ?string $thousandsSeparator = null,
        ?int $rawNumberDecimals = null,
        ?int $durationDecimals = null,
        ?int $operationsDecimals = null,
        ?int $progressTimeDecimals = null,
        ?int $progressOperationsDecimals = null,
        ?int $ratioDecimals = null,
        ?int $percentageDecimals = null,
        ?int $deltaPercentageDecimals = null,
        bool $significanceEnabled = false,
        bool $significanceEnabledIsSet = false,
        ?float $significanceAlpha = null,
        ?int $significanceMinimumSamples = null,
        ?CompatibilityMode $compatibilityMode = null,
    ): self {
        return new self(
            benchmarkPath: $benchmarkPath ?? $this->benchmarkPath,
            snapshotPath: $snapshotPath ?? $this->snapshotPath,
            runPath: $runPath ?? $this->runPath,
            preferredCompetitors: $preferredCompetitors ?? $this->preferredCompetitors,
            competitorAliases: $competitorAliases ?? $this->competitorAliases,
            scenarioReferences: $scenarioReferences ?? $this->scenarioReferences,
            bootstrapPath: $bootstrapPathIsSet ? $bootstrapPath : $this->bootstrapPath,
            defaultIterations: $defaultIterations ?? $this->defaultIterations,
            defaultRevolutions: $defaultRevolutions ?? $this->defaultRevolutions,
            defaultWarmupIterations: $defaultWarmupIterations ?? $this->defaultWarmupIterations,
            calibrationBudgetNanoseconds: $calibrationBudgetNanoseconds ?? $this->calibrationBudgetNanoseconds,
            processIsolation: $processIsolationIsSet ? $processIsolation : $this->processIsolation,
            defaultRegressionMetric: $defaultRegressionMetric ?? $this->defaultRegressionMetric,
            defaultRegressionTolerance: $defaultRegressionTolerance ?? $this->defaultRegressionTolerance,
            defaultReportFormat: $defaultReportFormat ?? $this->defaultReportFormat,
            progressMetric: $progressMetric ?? $this->progressMetric,
            progressTimeUnit: $progressTimeUnit ?? $this->progressTimeUnit,
            comparisonReference: $comparisonReference ?? $this->comparisonReference,
            decimalSeparator: $decimalSeparator ?? $this->decimalSeparator,
            thousandsSeparator: $thousandsSeparator ?? $this->thousandsSeparator,
            rawNumberDecimals: $rawNumberDecimals ?? $this->rawNumberDecimals,
            durationDecimals: $durationDecimals ?? $this->durationDecimals,
            operationsDecimals: $operationsDecimals ?? $this->operationsDecimals,
            progressTimeDecimals: $progressTimeDecimals ?? $this->progressTimeDecimals,
            progressOperationsDecimals: $progressOperationsDecimals ?? $this->progressOperationsDecimals,
            ratioDecimals: $ratioDecimals ?? $this->ratioDecimals,
            percentageDecimals: $percentageDecimals ?? $this->percentageDecimals,
            deltaPercentageDecimals: $deltaPercentageDecimals ?? $this->deltaPercentageDecimals,
            significanceEnabled: $significanceEnabledIsSet ? $significanceEnabled : $this->significanceEnabled,
            significanceAlpha: $significanceAlpha ?? $this->significanceAlpha,
            significanceMinimumSamples: $significanceMinimumSamples ?? $this->significanceMinimumSamples,
            compatibilityMode: $compatibilityMode ?? $this->compatibilityMode,
        );
    }
}
