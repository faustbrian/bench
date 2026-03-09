<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Comparison\SignificanceCalculator;
use Cline\Bench\Comparison\SignificanceStatus;

describe('SignificanceCalculator', function (): void {
    it('uses a configurable alpha threshold', function (): void {
        $baseline = [1.0, 2.0, 3.0, 4.0];
        $candidate = [3.0, 4.0, 5.0, 6.0];

        expect(
            new SignificanceCalculator(alpha: 0.10)->compare($baseline, $candidate),
        )
            ->toHaveProperty('status', SignificanceStatus::Significant)
            ->and(
                new SignificanceCalculator(alpha: 0.05)->compare($baseline, $candidate),
            )
            ->toHaveProperty('status', SignificanceStatus::NotSignificant);
    });

    it('can disable significance output entirely', function (): void {
        expect(
            new SignificanceCalculator(enabled: false)->compare([1.0, 2.0], [3.0, 4.0]),
        )
            ->toHaveProperty('status', SignificanceStatus::Disabled);
    });

    it('requires a configurable minimum sample size', function (): void {
        expect(
            new SignificanceCalculator(minimumSamples: 3)->compare([1.0, 2.0], [3.0, 4.0]),
        )
            ->toHaveProperty('status', SignificanceStatus::NotAvailable)
            ->and(
                new SignificanceCalculator(minimumSamples: 2)->compare([1.0, 2.0], [3.0, 4.0]),
            )
            ->not->toHaveProperty('status', SignificanceStatus::NotAvailable);
    });
});
