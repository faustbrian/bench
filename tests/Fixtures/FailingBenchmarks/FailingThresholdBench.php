<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\FailingBenchmarks;

use Cline\Bench\Attributes\Bench;
use Cline\Bench\Attributes\Iterations;
use Cline\Bench\Attributes\Revolutions;
use Cline\Bench\Attributes\Threshold;
use Cline\Bench\Enums\Metric;
use Cline\Bench\Enums\ThresholdOperator;
use RuntimeException;

use function throw_if;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class FailingThresholdBench
{
    #[Bench('failing-threshold')]
    #[Iterations(1)]
    #[Revolutions(1)]
    #[Threshold(Metric::Median, ThresholdOperator::LessThan, 0.0)]
    public function benchFailingThreshold(): void
    {
        $value = 1 + 1;

        throw_if($value === 0, RuntimeException::class);
    }
}
