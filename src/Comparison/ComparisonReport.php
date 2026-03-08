<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Comparison;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ComparisonReport
{
    /**
     * @param list<ComparisonRow> $rows
     */
    public function __construct(
        public array $rows,
        public float $geometricMeanSpeedRatio,
    ) {}
}
