<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Enums;

/**
 * @author Brian Faust <brian@cline.sh>
 */
enum Metric: string
{
    case Minimum = 'min';
    case Maximum = 'max';
    case Mean = 'mean';
    case Median = 'median';
    case Percentile75 = 'p75';
    case Percentile95 = 'p95';
    case Percentile99 = 'p99';
    case OperationsPerSecond = 'ops/s';
}
