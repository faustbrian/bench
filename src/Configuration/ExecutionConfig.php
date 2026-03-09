<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Configuration;

use Cline\Bench\Enums\Metric;
use Cline\Bench\Environment\CompatibilityMode;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ExecutionConfig
{
    public function __construct(
        public int $defaultIterations,
        public int $defaultRevolutions,
        public int $defaultWarmupIterations,
        public int $calibrationBudgetNanoseconds,
        public bool $processIsolation,
        public Metric $defaultRegressionMetric,
        public string $defaultRegressionTolerance,
        public CompatibilityMode $compatibilityMode,
    ) {}
}
