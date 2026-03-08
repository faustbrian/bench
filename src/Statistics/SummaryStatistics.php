<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Statistics;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class SummaryStatistics
{
    public function __construct(
        public int $samples,
        public float $min,
        public float $max,
        public float $mean,
        public float $median,
        public float $standardDeviation,
        public float $relativeMarginOfError,
        public float $percentile75,
        public float $percentile95,
        public float $percentile99,
        public float $operationsPerSecond,
    ) {}

    /**
     * @return array<string, float|int>
     */
    public function toArray(): array
    {
        return [
            'samples' => $this->samples,
            'min' => $this->min,
            'max' => $this->max,
            'mean' => $this->mean,
            'median' => $this->median,
            'standard_deviation' => $this->standardDeviation,
            'relative_margin_of_error' => $this->relativeMarginOfError,
            'percentile75' => $this->percentile75,
            'percentile95' => $this->percentile95,
            'percentile99' => $this->percentile99,
            'operations_per_second' => $this->operationsPerSecond,
        ];
    }
}
