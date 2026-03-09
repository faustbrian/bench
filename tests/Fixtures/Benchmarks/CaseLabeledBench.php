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
use Cline\Bench\Attributes\Params;
use Cline\Bench\Attributes\Revolutions;
use Cline\Bench\Attributes\Scenario;

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[Scenario('dto-transform')]
#[Competitor('bench')]
final class CaseLabeledBench
{
    /** @var list<string> */
    public static array $sizes = [];

    public static function reset(): void
    {
        self::$sizes = [];
    }

    #[Bench('transform-case')]
    #[Iterations(1)]
    #[Revolutions(1)]
    #[Params([
        ['_case' => 'small-payload', 'size' => 'small'],
        ['_case' => 'large-payload', 'size' => 'large'],
    ])]
    public function benchTransformCase(string $size): void
    {
        self::$sizes[] = $size;
    }
}
