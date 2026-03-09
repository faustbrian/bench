<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Comparison;

use Cline\Bench\Execution\BenchmarkResult;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ComparisonRow
{
    public function __construct(
        public BenchmarkResult $result,
        public string $winner,
        public float $deltaPercentage,
        public float $referenceGap,
        public float $referenceGain,
        public ?string $significance = null,
    ) {}
}
