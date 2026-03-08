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

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[Scenario('isolation')]
#[Competitor('bench')]
final class IsolatedBench
{
    public static int $parentProcessCalls = 0;

    public static function reset(): void
    {
        self::$parentProcessCalls = 0;
    }

    #[Bench('isolated-call')]
    public function benchIsolatedCall(): void
    {
        ++self::$parentProcessCalls;
    }
}
