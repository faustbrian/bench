<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Configuration;

use Cline\Bench\Enums\Metric;
use Cline\Bench\Enums\ReportFormat;
use Cline\Bench\Enums\TimeUnit;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ReportingConfig
{
    public function __construct(
        public ReportFormat $defaultReportFormat,
        public Metric $progressMetric,
        public TimeUnit $progressTimeUnit,
        public string $decimalSeparator,
        public string $thousandsSeparator,
        public int $rawNumberDecimals,
        public int $durationDecimals,
        public int $operationsDecimals,
        public int $progressTimeDecimals,
        public int $progressOperationsDecimals,
        public int $ratioDecimals,
        public int $percentageDecimals,
        public int $deltaPercentageDecimals,
    ) {}
}
