<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Snapshot;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class RegressionDecision
{
    public function __construct(
        public bool $passed,
        public string $metric,
        public float $deltaPercentage,
        public float $tolerancePercentage,
    ) {}
}
