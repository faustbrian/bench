<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Discovery\BenchmarkDiscovery;
use Tests\Fixtures\Benchmarks\DefaultBench;
use Tests\Fixtures\Benchmarks\ParameterizedBench;

describe('BenchmarkDiscovery', function (): void {
    it('returns an empty list for unknown paths', function (): void {
        $benchmarks = new BenchmarkDiscovery()->discover(__DIR__.'/../../Fixtures/does-not-exist');

        expect($benchmarks)->toBe([]);
    });

    it('derives default scenario and competitor names when attributes are absent', function (): void {
        $benchmarks = new BenchmarkDiscovery()->discover(__DIR__.'/../../Fixtures/Benchmarks');
        $defaultBench = null;

        foreach ($benchmarks as $benchmark) {
            if ($benchmark->className !== DefaultBench::class) {
                continue;
            }

            $defaultBench = $benchmark;

            break;
        }

        expect($defaultBench)->not->toBeNull()
            ->and($defaultBench->scenario)->toBe('DefaultBench')
            ->and($defaultBench->competitor)->toBe('DefaultBench')
            ->and($defaultBench->subject)->toBe('default');
    });

    it('discovers groups, params, assertions, and regression metadata', function (): void {
        $benchmarks = new BenchmarkDiscovery()->discover(__DIR__.'/../../Fixtures/Benchmarks');
        $parameterizedBench = null;

        foreach ($benchmarks as $benchmark) {
            if ($benchmark->className !== ParameterizedBench::class) {
                continue;
            }

            if ($benchmark->methodName !== 'benchTransformPayload') {
                continue;
            }

            $parameterizedBench = $benchmark;

            break;
        }

        expect($parameterizedBench)->not->toBeNull()
            ->and($parameterizedBench->groups)->toBe(['dto', 'comparison'])
            ->and($parameterizedBench->parameterSets)->toBe([
                ['size' => 'small', 'multiplier' => 10],
                ['size' => 'large', 'multiplier' => 100],
            ])
            ->and($parameterizedBench->regressionMetric)->toBe('median')
            ->and($parameterizedBench->regressionTolerance)->toBe('7%')
            ->and($parameterizedBench->assertions)->toHaveCount(1)
            ->and($parameterizedBench->assertions[0]->metric)->toBe('median');
    });
});
