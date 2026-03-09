<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Benchmarks;

use Cline\Bench\Attributes\After;
use Cline\Bench\Attributes\Before;
use Cline\Bench\Attributes\Bench;
use Cline\Bench\Attributes\Competitor;
use Cline\Bench\Attributes\Iterations;
use Cline\Bench\Attributes\Revolutions;
use Cline\Bench\Attributes\Scenario;
use Cline\Bench\Attributes\Warmup;

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[Scenario('hooked-scenario')]
#[Competitor('hooked')]
final class HookedBench
{
    public static int $beforeCalls = 0;

    public static int $afterCalls = 0;

    public static int $subjectCalls = 0;

    public static function reset(): void
    {
        self::$beforeCalls = 0;
        self::$afterCalls = 0;
        self::$subjectCalls = 0;
    }

    #[Bench('hooked')]
    #[Warmup(1)]
    #[Iterations(2)]
    #[Revolutions(2)]
    #[Before(['beforeRun'])]
    #[After(['afterRun'])]
    public function benchHooked(): void
    {
        ++self::$subjectCalls;
    }

    public function beforeRun(): void
    {
        ++self::$beforeCalls;
    }

    public function afterRun(): void
    {
        ++self::$afterCalls;
    }
}
