<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Benchmarks;

use Cline\Bench\Attributes\Bench;

use function mb_strlen;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class DefaultBench
{
    #[Bench()]
    public function benchDefault(): void
    {
        mb_strlen('default');
    }
}
