<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Execution;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class AssertionResult
{
    public function __construct(
        public string $metric,
        public string $operator,
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
            'metric' => $this->metric,
            'operator' => $this->operator,
            'expected' => $this->expected,
            'actual' => $this->actual,
            'passed' => $this->passed,
        ];
    }
}
