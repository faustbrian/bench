<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\FailingBenchmarks;

use Cline\Bench\Attributes\Assert;
use Cline\Bench\Attributes\Bench;
use Cline\Bench\Attributes\Iterations;
use Cline\Bench\Attributes\Revs;
use Cline\Bench\Enums\AssertionOperator;
use Cline\Bench\Enums\Metric;
use RuntimeException;

use function throw_if;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class FailingAssertBench
{
    #[Bench('failing-assertion')]
    #[Iterations(1)]
    #[Revs(1)]
    #[Assert(Metric::Median, AssertionOperator::LessThan, 0.0)]
    public function benchFailingAssertion(): void
    {
        $value = 1 + 1;

        throw_if($value === 0, RuntimeException::class);
    }
}
