<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Benchmarks;

use Cline\Bench\Attributes\Assert;
use Cline\Bench\Attributes\Bench;
use Cline\Bench\Attributes\Competitor;
use Cline\Bench\Attributes\Group;
use Cline\Bench\Attributes\Iterations;
use Cline\Bench\Attributes\Params;
use Cline\Bench\Attributes\Regression;
use Cline\Bench\Attributes\Revolutions;
use Cline\Bench\Attributes\Scenario;
use Cline\Bench\Enums\AssertionOperator;
use Cline\Bench\Enums\Metric;
use RuntimeException;

use function throw_if;

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[Scenario('dto-transform')]
#[Competitor('bench')]
#[Group(['dto', 'comparison'])]
final class ParameterizedBench
{
    /** @var list<string> */
    public static array $sizes = [];

    public static function reset(): void
    {
        self::$sizes = [];
    }

    #[Bench('transform-payload')]
    #[Iterations(1)]
    #[Revolutions(1)]
    #[Params([
        ['size' => 'small', 'multiplier' => 10],
        ['size' => 'large', 'multiplier' => 100],
    ])]
    #[Regression(metric: Metric::Median, tolerance: '7%')]
    #[Assert(Metric::Median, AssertionOperator::LessThan, 10_000_000.0)]
    public function benchTransformPayload(string $size, int $multiplier): void
    {
        self::$sizes[] = $size;

        $payload = 0;

        for ($index = 0; $index < $multiplier; ++$index) {
            $payload += $index;
        }

        throw_if($payload < 0, RuntimeException::class, 'unreachable');
    }
}
