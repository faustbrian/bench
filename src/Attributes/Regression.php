<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Attributes;

use Attribute;
use Cline\Bench\Enums\Metric;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Regression
{
    public function __construct(
        public Metric $metric = Metric::Median,
        public string $tolerance = '5%',
    ) {}
}
