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
use Cline\Bench\Attributes\Revolutions;
use Cline\Bench\Attributes\Scenario;

use function hash;
use function str_repeat;

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[Scenario('dto-transform')]
#[Competitor('bench')]
final class TransformBench
{
    #[Bench('transform')]
    #[Iterations(3)]
    #[Revolutions(10)]
    public function benchTransform(): void
    {
        hash('xxh128', str_repeat('bench', 8));
    }
}
