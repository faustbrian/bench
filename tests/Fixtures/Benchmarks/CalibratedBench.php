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
use Cline\Bench\Attributes\Scenario;

use function time_nanosleep;

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[Scenario('calibration')]
#[Competitor('bench')]
final class CalibratedBench
{
    public static int $subjectCalls = 0;

    public static function reset(): void
    {
        self::$subjectCalls = 0;
    }

    #[Bench('sleepy-loop')]
    public function benchSleepyLoop(): void
    {
        ++self::$subjectCalls;

        time_nanosleep(0, 1_000_000);
    }
}
