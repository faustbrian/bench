<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Execution;

use Cline\Bench\Enums\AssertionOperator;
use Cline\Bench\Enums\Metric;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class AssertionResult
{
    public function __construct(
        public Metric $metric,
        public AssertionOperator $operator,
        public float $expected,
        public float $actual,
        public bool $passed,
    ) {}

    /**
     * @return array<string, bool|float|string>
     */
    public function toArray(): array
    {
        return [
            'metric' => $this->metric->value,
            'operator' => $this->operator->value,
            'expected' => $this->expected,
            'actual' => $this->actual,
            'passed' => $this->passed,
        ];
    }
}
