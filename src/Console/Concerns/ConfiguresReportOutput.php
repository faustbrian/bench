<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Console\Concerns;

use Cline\Bench\Configuration\BenchConfig;
use Cline\Bench\Configuration\ComparisonConfig;
use Cline\Bench\Configuration\ReportingConfig;
use Cline\Bench\Enums\ComparisonReference;
use Cline\Bench\Enums\Metric;
use Cline\Bench\Enums\TimeUnit;

/**
 * @author Brian Faust <brian@cline.sh>
 */
trait ConfiguresReportOutput
{
    private ComparisonConfig $comparisonConfig;

    private ReportingConfig $reportingConfig;

    private bool $significanceOutputEnabled = true;

    protected function initializeReportOutput(BenchConfig $config, bool $disableSignificance = false): void
    {
        $this->comparisonConfig = $config->comparison();
        $this->reportingConfig = $config->reporting();
        $this->significanceOutputEnabled = $this->comparisonConfig->significanceEnabled && !$disableSignificance;
    }

    /**
     * @return list<string>
     */
    protected function preferredCompetitors(): array
    {
        return $this->comparisonConfig->preferredCompetitors;
    }

    /**
     * @return array<string, string>
     */
    protected function competitorAliases(): array
    {
        return $this->comparisonConfig->competitorAliases;
    }

    protected function comparisonReference(): ComparisonReference
    {
        return $this->comparisonConfig->comparisonReference;
    }

    protected function decimalSeparator(): string
    {
        return $this->reportingConfig->decimalSeparator;
    }

    protected function thousandsSeparator(): string
    {
        return $this->reportingConfig->thousandsSeparator;
    }

    protected function rawNumberDecimals(): int
    {
        return $this->reportingConfig->rawNumberDecimals;
    }

    protected function durationDecimals(): int
    {
        return $this->reportingConfig->durationDecimals;
    }

    protected function operationsDecimals(): int
    {
        return $this->reportingConfig->operationsDecimals;
    }

    protected function ratioDecimals(): int
    {
        return $this->reportingConfig->ratioDecimals;
    }

    protected function percentageDecimals(): int
    {
        return $this->reportingConfig->percentageDecimals;
    }

    protected function deltaPercentageDecimals(): int
    {
        return $this->reportingConfig->deltaPercentageDecimals;
    }

    protected function significanceEnabled(): bool
    {
        return $this->significanceOutputEnabled;
    }

    protected function significanceAlpha(): float
    {
        return $this->comparisonConfig->significanceAlpha;
    }

    protected function significanceMinimumSamples(): int
    {
        return $this->comparisonConfig->significanceMinimumSamples;
    }

    protected function progressMetric(): Metric
    {
        return $this->reportingConfig->progressMetric;
    }

    protected function progressTimeUnit(): TimeUnit
    {
        return $this->reportingConfig->progressTimeUnit;
    }

    protected function progressTimeDecimals(): int
    {
        return $this->reportingConfig->progressTimeDecimals;
    }

    protected function progressOperationsDecimals(): int
    {
        return $this->reportingConfig->progressOperationsDecimals;
    }
}
