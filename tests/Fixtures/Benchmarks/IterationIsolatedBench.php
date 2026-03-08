<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Benchmarks;

use Cline\Bench\Attributes\Bench;
use Cline\Bench\Attributes\Competitor;
use Cline\Bench\Attributes\Iterations;
use Cline\Bench\Attributes\Scenario;

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[Scenario('iteration-isolation')]
#[Competitor('bench')]
#[Iterations(3)]
final class IterationIsolatedBench
{
    public static int $instances = 0;

    public static int $runs = 0;

    public function __construct()
    {
        ++self::$instances;
    }

    public static function reset(): void
    {
        self::$instances = 0;
        self::$runs = 0;
    }

    #[Bench('fresh-instance-per-iteration')]
    public function benchFreshInstancePerIteration(): void
    {
        ++self::$runs;
    }
}
