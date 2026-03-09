<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Comparison;

use function sprintf;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class SignificanceResult
{
    public function __construct(
        public SignificanceStatus $status,
        public ?float $pValue,
        public ?float $alpha,
        public ?int $minimumSamples,
    ) {}

    public static function winner(?float $alpha = null, ?int $minimumSamples = null): self
    {
        return new self(SignificanceStatus::Winner, null, $alpha, $minimumSamples);
    }

    public static function significant(float $pValue, float $alpha, int $minimumSamples): self
    {
        return new self(SignificanceStatus::Significant, $pValue, $alpha, $minimumSamples);
    }

    public static function notSignificant(float $pValue, float $alpha, int $minimumSamples): self
    {
        return new self(SignificanceStatus::NotSignificant, $pValue, $alpha, $minimumSamples);
    }

    public static function disabled(float $alpha, int $minimumSamples): self
    {
        return new self(SignificanceStatus::Disabled, null, $alpha, $minimumSamples);
    }

    public static function notAvailable(float $alpha, int $minimumSamples): self
    {
        return new self(SignificanceStatus::NotAvailable, null, $alpha, $minimumSamples);
    }

    public function label(): string
    {
        return match ($this->status) {
            SignificanceStatus::Winner => 'fastest',
            SignificanceStatus::Disabled => 'disabled',
            SignificanceStatus::NotAvailable => 'n/a',
            SignificanceStatus::Significant => sprintf('significant (p=%.3f)', $this->pValue),
            SignificanceStatus::NotSignificant => sprintf('not significant (p=%.3f)', $this->pValue),
        };
    }

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     p_value: null|float,
     *     alpha: null|float,
     *     minimum_samples: null|int
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'label' => $this->label(),
            'p_value' => $this->pValue,
            'alpha' => $this->alpha,
            'minimum_samples' => $this->minimumSamples,
        ];
    }
}
